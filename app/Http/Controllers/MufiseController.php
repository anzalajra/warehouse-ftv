<?php

namespace App\Http\Controllers;

use App\Models\MufiseOperation;
use App\Models\Rental;
use App\Services\Mufise\MufiseConnectionService;
use App\Services\Mufise\MufiseException;
use App\Services\Mufise\MufiseRentalService;
use App\Services\Mufise\MufiseSettings;
use App\Services\MufiseClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MufiseController extends Controller
{
    public function __construct(private MufiseClient $client, private MufiseConnectionService $connections, private MufiseRentalService $rentals) {}

    public function index()
    {
        $status = null;
        $projects = [];
        $error = null;
        $pending = null;
        if (MufiseSettings::enabled()) {
            try {
                $account = $this->connections->account(Auth::guard('customer')->id());
                $pairing = $account->pairing;
                if ($pairing && now()->lt($pairing['expires_at'])) {
                    $pending = [
                        'verification_url' => MufiseSettings::url().'/connect/warehouse?request='.rawurlencode($pairing['request_id']),
                        'expires_at' => $pairing['expires_at'],
                    ];
                }
                if ($account->access_token && $account->expires_at?->isFuture()) {
                    $token = $this->connections->token($account);
                    $status = $this->client->request('GET', 'me', $token);
                    if (($status['next_action'] ?? '') === 'ready') {
                        $projects = $this->client->request('GET', 'projects', $token)['projects'] ?? [];
                    }
                }
            } catch (MufiseException $e) {
                $error = $e->getMessage();
            }
        }

        return response()->view('frontend.mufise.connections', compact('status', 'projects', 'error', 'pending'))->header('Cache-Control', 'private, no-store');
    }

    public function start()
    {
        return $this->jsonAction(fn () => $this->connections->start(Auth::guard('customer')->user()));
    }

    public function poll()
    {
        return $this->jsonAction(fn () => $this->connections->poll(Auth::guard('customer')->id()));
    }

    public function disconnect()
    {
        return $this->jsonAction(function () {
            $this->connections->disconnect(Auth::guard('customer')->id());

            return ['connected' => false];
        });
    }

    private function jsonAction(callable $action)
    {
        try {
            return response()->json($action())->header('Cache-Control', 'private, no-store');
        } catch (MufiseException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->status)->withHeaders(['Cache-Control' => 'private, no-store', 'Retry-After' => $e->retryAfter]);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return response()->json(['message' => 'Permintaan lain sedang diproses. Coba kembali.', 'code' => 'busy'], 429)->header('Cache-Control', 'private, no-store');
        }
    }

    private function context(Request $request, bool $write = false): array
    {
        if ($request->route('rental')) {
            $rental = Rental::findOrFail($request->route('rental'));
            if ($request->routeIs('admin.mufise', 'admin.mufise.*')) {
                Gate::authorize($write ? 'update' : 'view', $rental);
            } else {
                abort_unless((int) $rental->user_id === Auth::guard('customer')->id(), 403);
            }
            abort_unless($rental->mufise_required, 404);
            if ($write) {
                abort_unless(in_array($rental->status, [Rental::STATUS_QUOTATION, Rental::STATUS_CONFIRMED, Rental::STATUS_LATE_PICKUP], true), 403);
            }

            return [$this->rentals->accountFor($rental), $rental->mufise_project_id, $rental];
        }

        return [$this->connections->account(Auth::guard('customer')->id()), $request->route('project'), null];
    }

    public function project(Request $request)
    {
        try {
            [$account, $projectId, $rental] = $this->context($request);
            $bundle = $this->rentals->project($account, $projectId);
            $positions = ($bundle['permissions']['can_edit'] ?? false)
                ? $this->client->request('GET', 'crew-positions', $this->connections->token($account))['positions'] ?? [] : [];
            $base = $rental ? ($request->routeIs('admin.*') ? 'admin.mufise' : 'customer.mufise.rental') : 'customer.mufise.project';
            $params = $rental ? ['rental' => $rental->id] : ['project' => $projectId];

            return response()->view('frontend.mufise.project', compact('bundle', 'positions', 'rental', 'base', 'params'))->header('Cache-Control', 'private, no-store');
        } catch (MufiseException $e) {
            return response()->view('frontend.mufise.error', ['message' => $e->getMessage()], $e->status)->header('Cache-Control', 'private, no-store');
        }
    }

    public function callSheet(Request $request)
    {
        try {
            [$account, $projectId] = $this->context($request);
            $bundle = $this->rentals->project($account, $projectId);
            $dayId = $request->route('day');
            abort_unless(collect($bundle['call_sheets'] ?? [])->contains('day_id', $dayId), 404);
            $html = $this->client->request('GET', 'projects/'.rawurlencode($projectId).'/call-sheets/'.rawurlencode($dayId), $this->connections->token($account), ['format' => 'html']);

            return response($html)->withHeaders([
                'Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox",
            ]);
        } catch (MufiseException $e) {
            return response()->view('frontend.mufise.error', ['message' => $e->getMessage()], $e->status)->header('Cache-Control', 'private, no-store');
        }
    }

    public function mutate(Request $request)
    {
        try {
            [$account, $projectId] = $this->context($request, true);
            $validated = $request->validate([
                'operation_id' => 'required|uuid', 'kind' => 'required|in:crew,locations', 'target' => 'nullable|uuid',
                'full_name' => 'nullable|string|max:120', 'role' => 'nullable|string|max:100', 'department' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:255', 'position_id' => 'nullable|string|max:120',
                'person_id' => 'nullable|uuid',
                'reason' => 'nullable|string|min:3|max:500', 'expected_version' => 'nullable|string|max:200', 'confirm_distinct' => 'nullable|boolean',
                'name' => 'nullable|string|max:255', 'address' => 'nullable|string|max:2000', 'parking' => 'nullable|string|max:500', 'hospital' => 'nullable|string|max:500',
            ]);
            $kind = $validated['kind'];
            $target = $validated['target'] ?? null;
            $fields = $kind === 'crew' ? ['full_name', 'phone', 'email', 'confirm_distinct'] : ['name', 'address', 'parking', 'hospital'];
            $assignExisting = $kind === 'crew' && ! $target && ! empty($validated['person_id']);
            $fields = array_merge($fields, $target ? ['expected_version', ...($kind === 'crew' ? ['position_id', 'reason'] : [])] : ($kind === 'crew' ? ['role', 'department'] : []));
            $payload = $assignExisting
                ? ['person_id' => $validated['person_id']]
                : array_intersect_key($validated, array_flip($fields));
            foreach ($payload as $key => $value) {
                if ($key === 'confirm_distinct') {
                    $payload[$key] = (bool) $value;
                } elseif ($value === null) {
                    if (in_array($key, ['position_id', 'phone'], true)) {
                        unset($payload[$key]);
                    } else {
                        $payload[$key] = '';
                    }
                }
            }
            if ((! $target && $kind === 'crew' && ! $assignExisting && (empty($payload['full_name']) || empty($payload['phone']) || empty($payload['role']))) || ($kind === 'locations' && (empty($payload['name']) || empty($payload['address'])))) {
                throw ValidationException::withMessages(['mutation' => 'Lengkapi nama, telepon dan jabatan kru, atau nama dan alamat lokasi.']);
            }
            if ($target && (empty($payload['expected_version']) || ($kind === 'crew' && empty($payload['reason'])))) {
                throw ValidationException::withMessages(['mutation' => 'Version dan alasan perubahan kru wajib diisi.']);
            }
            $path = 'projects/'.rawurlencode($projectId).'/'.$kind.($target ? '/'.rawurlencode($target) : '');
            $method = $target ? 'PATCH' : 'POST';
            $id = $validated['operation_id'];
            Cache::lock('mufise-operation-'.$id, 60)->block(1, function () use ($id, $account, $projectId, $path, $method, $payload) {
                $operation = MufiseOperation::find($id);
                if ($operation) {
                    abort_unless($operation->mufise_account_id === $account->id && $operation->project_id === $projectId && $operation->path === $path && $operation->method === $method && $operation->payload === $payload, 409);
                } else {
                    $operation = MufiseOperation::create(['id' => $id, 'mufise_account_id' => $account->id, 'project_id' => $projectId, 'path' => $path, 'method' => $method, 'payload' => $payload]);
                }
                if (! $operation->completed_at) {
                    $this->client->request($method, $path, $this->connections->token($account), $operation->payload, $id);
                    $operation->update(['completed_at' => now()]);
                }
            });

            return back()->with('success', 'Data berhasil disimpan di Mufise.');
        } catch (MufiseException $e) {
            return back()->withErrors(['mutation' => $e->getMessage()])->withInput();
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return back()->withErrors(['mutation' => 'Operasi sedang diproses. Coba kembali dengan formulir yang sama.'])->withInput();
        }
    }
}
