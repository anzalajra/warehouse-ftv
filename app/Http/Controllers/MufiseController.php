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

    public function index(Request $request)
    {
        $status = null;
        $projects = [];
        $error = null;
        $problem = null;
        $pending = null;
        if (MufiseSettings::enabled()) {
            try {
                $account = $this->connections->account(Auth::guard('customer')->id());
                $pairing = $this->connections->pairing($account);
                if ($pairing && now()->lt($pairing['expires_at'])) {
                    $pending = [
                        'verification_url' => MufiseSettings::url().'/connect/warehouse?request='.rawurlencode($pairing['request_id']),
                        'expires_at' => $pairing['expires_at'],
                    ];
                }
                if ($this->connections->storedToken($account)) {
                    $token = $this->connections->token($account);
                    $status = $this->client->request('GET', 'me', $token);
                    if (($status['next_action'] ?? '') === 'ready') {
                        $projects = $this->client->request('GET', 'projects', $token)['projects'] ?? [];
                    }
                }
            } catch (MufiseException $e) {
                $error = $e->getMessage();
                $problem = $e->problem();
            }
        }

        if ($request->expectsJson()) {
            return response()->json(compact('status', 'projects', 'error', 'problem'))->header('Cache-Control', 'private, no-store');
        }

        return response()->view('frontend.mufise.connections', compact('status', 'projects', 'error', 'pending', 'problem'))->header('Cache-Control', 'private, no-store');
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
            $response = response()->json($e->problem(), $e->status)->header('Cache-Control', 'private, no-store');
            if ($e->retryable) {
                $response->header('Retry-After', $e->retryAfter);
            }

            return $response;
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
            $base = $rental ? ($request->routeIs('admin.*') ? 'admin.mufise' : 'customer.mufise.rental') : 'customer.mufise.project';
            $params = $rental ? ['rental' => $rental->id] : ['project' => $projectId];

            $editorConfig = $this->editorConfig($base, $params, $projectId, $rental);

            return response()->view('frontend.mufise.project', compact('bundle', 'rental', 'base', 'params', 'editorConfig'))->header('Cache-Control', 'private, no-store');
        } catch (MufiseException $e) {
            return response()->view('frontend.mufise.error', ['message' => $e->getMessage(), 'problem' => $e->problem()], $e->status)->header('Cache-Control', 'private, no-store');
        }
    }

    private function editorConfig(string $base, array $params, string $projectId, ?Rental $rental): array
    {
        return [
            'projectId' => $projectId, 'apiUrl' => route($base.'.api', $params), 'mediaUrl' => route($base.'.media', $params),
            'mufiseUrl' => MufiseSettings::url().'/',
            'connectionUrl' => route('customer.mufise.index'), 'pollSeconds' => MufiseSettings::pollSeconds(),
            'writable' => ! $rental || in_array($rental->status, ['quotation', 'confirmed', 'late_pickup'], true),
        ];
    }

    public function editorApi(Request $request)
    {
        return $this->jsonAction(function () use ($request) {
            if ($request->isJson()) {
                // Preserve intentional empty strings/arrays in Mufise patches. Laravel's
                // ConvertEmptyStringsToNull must not change the remote mutation body.
                $original = json_decode($request->getContent(), true);
                if (is_array($original) && isset($original['data']) && is_array($original['data'])) {
                    $request->merge(['data' => $original['data']]);
                }
            }
            if ($request->has('payload_json')) {
                $request->validate(['payload_json' => 'required|string|max:65536']);
                $payload = json_decode($request->input('payload_json'), true);
                abort_unless(is_array($payload), 422);
                $request->merge(['data' => $payload]);
            }
            $input = $request->validate([
                'endpoint' => 'required|string|max:200', 'method' => 'sometimes|in:GET,POST,PATCH,DELETE',
                'data' => 'sometimes|array', 'operation_id' => 'nullable|uuid',
                'photo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:4882',
            ]);
            $method = $request->isMethod('GET') ? 'GET' : ($input['method'] ?? 'POST');
            $endpoint = $input['endpoint'];
            $uuid = '[a-fA-F0-9-]{36}';
            $read = '~^(editor|sync|crew-positions|organization-crew(?:/'.$uuid.')?|crew/'.$uuid.'(?:/availability)?|locations/'.$uuid.'|call-sheet-editor|call-sheets/'.$uuid.'|geocode)$~';
            $write = '~^(crew(?:/preview|/'.$uuid.'(?:/availability)?)?|locations(?:/'.$uuid.'(?:/points/(?:shoot|parking|hospital)|/readiness/(?:check|recce|lock)|/availability)?)?|call-sheets(?:/'.$uuid.'(?:/lock)?)?)$~';
            abort_unless(preg_match($method === 'GET' ? $read : $write, $endpoint), 422);
            abort_if($method !== 'GET' && $request->isMethod('GET'), 405);
            [$account, $projectId] = $this->context($request, $method !== 'GET');
            $token = $this->connections->token($account);
            $path = $endpoint === 'crew-positions' ? $endpoint : 'projects/'.rawurlencode($projectId).'/'.$endpoint;
            $data = $input['data'] ?? [];
            // GET query fields are deliberately limited; no arbitrary URL/header forwarding.
            if ($method === 'GET') {
                $data = $request->validate(['q' => 'nullable|string|max:120', 'page' => 'nullable|integer|min:1|max:10000', 'limit' => 'nullable|integer|min:1|max:50', 'since' => 'nullable|string|size:64']);
                $result = $this->client->request('GET', $path, $token, $data);
                if ($endpoint === 'editor') {
                    $result['draft_scope'] = hash_hmac('sha256', $account->id.'|'.$projectId.'|'.MufiseSettings::url().'|'.MufiseSettings::connectionId().'|'.$token, config('app.key'));
                }
                if ($endpoint === 'sync') {
                    $result['warehouse_requirements'] = MufiseSettings::bookingRequirements();
                    if ($result['changed'] ?? false) {
                        $result['warehouse_missing'] = $this->rentals->localMissing($result);
                    }
                }

                return $result;
            }
            abort_if(strlen(json_encode($data)) > 65536, 413);
            $photo = null;
            if ($file = $request->file('photo')) {
                abort_unless(preg_match('~^crew(?:/'.$uuid.')?$~', $endpoint) && in_array($method, ['POST', 'PATCH'], true), 422);
                abort_unless(in_array($file->getMimeType(), ['image/png', 'image/jpeg', 'image/webp'], true) && $file->getSize() <= 5000000, 422);
                $photo = ['contents' => base64_encode($file->get()), 'name' => 'crew.'.match ($file->getMimeType()) {
                    'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg'
                }, 'mime' => $file->getMimeType()];
            }
            if ($endpoint === 'crew/preview') {
                abort_unless($method === 'POST' && ! $photo, 422);

                return $this->client->request('POST', $path, $token, $data);
            }
            $id = $input['operation_id'] ?? null;
            abort_unless($id, 422);

            // The remote API rechecks live resource permissions, membership and consent,
            // including idempotent retries. Local receipts retain the identical payload.
            return Cache::lock('mufise-operation-'.$id, 60)->block(1, function () use ($id, $account, $projectId, $path, $method, $data, $photo, $token) {
                $stored = ['data' => $data, 'photo' => $photo];
                $operation = MufiseOperation::find($id);
                if ($operation) {
                    abort_unless((int) $operation->mufise_account_id === (int) $account->id && $operation->project_id === $projectId && $operation->path === $path && $operation->method === $method && $operation->payload === $stored, 409);
                } else {
                    $operation = MufiseOperation::create(['id' => $id, 'mufise_account_id' => $account->id, 'project_id' => $projectId, 'path' => $path, 'method' => $method, 'payload' => $stored]);
                }
                $result = $this->client->request($method, $path, $token, $data, $id, $photo);
                $operation->update(['completed_at' => now()]);

                return $result;
            });
        });
    }

    public function media(Request $request)
    {
        try {
            [$account, $projectId] = $this->context($request);
            $endpoint = $request->validate(['endpoint' => 'required|string|max:200', 'format' => 'nullable|in:html,pdf'])['endpoint'];
            abort_unless(preg_match('~^(?:(crew|organization-crew)/([a-fA-F0-9-]{36})/photo|call-sheets/([a-fA-F0-9-]{36}))$~', $endpoint), 422);
            $photo = str_ends_with($endpoint, '/photo');
            $format = $photo ? 'photo' : $request->input('format', 'html');
            $result = $this->client->request('GET', 'projects/'.rawurlencode($projectId).'/'.$endpoint, $this->connections->token($account), $photo ? [] : ['format' => $format]);
            $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
            if ($photo) {
                return response($result['bytes'])->withHeaders($headers + ['Content-Type' => $result['mime']]);
            }
            if ($format === 'pdf') {
                return response($result)->withHeaders($headers + ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="call-sheet.pdf"']);
            }

            return response($result)->withHeaders($headers + ['Content-Type' => 'text/html; charset=utf-8', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox"]);
        } catch (MufiseException $e) {
            return response()->view('frontend.mufise.error', ['message' => $e->getMessage(), 'problem' => $e->problem()], $e->status)->header('Cache-Control', 'private, no-store');
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
            return response()->view('frontend.mufise.error', ['message' => $e->getMessage(), 'problem' => $e->problem()], $e->status)->header('Cache-Control', 'private, no-store');
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
