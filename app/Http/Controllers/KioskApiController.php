<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\ComputerBooking;
use App\Models\KioskCommand;
use App\Models\KioskPairingCode;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KioskApiController extends Controller
{
    public function pair(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $entry = KioskPairingCode::with('computer')
            ->where('code', $validated['code'])
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $entry || ! $entry->computer) {
            return response()->json(['error' => 'invalid_or_expired_code'], 422);
        }

        $computer = $entry->computer;
        $computer->kiosk_token = Str::random(64);
        $computer->kiosk_paired_at = now();
        $computer->save();

        $entry->used_at = now();
        $entry->save();

        return response()->json([
            'slug' => $computer->checkin_slug,
            'token' => $computer->kiosk_token,
            'kiosk_url' => $computer->checkinUrl(),
            'heartbeat_url' => url('/api/kiosk/heartbeat'),
            'update_url' => url('/api/kiosk/update'),
            'heartbeat_interval' => (int) (Setting::get('computer_kiosk_heartbeat_interval_seconds') ?? 30),
            'running_apps_whitelist' => $this->whitelist(),
            'computer_name' => $computer->name,
            'admin_pin' => Setting::get('computer_kiosk_admin_pin') ?? '9999',
        ]);
    }

    /**
     * Sync queued offline events from a kiosk. Idempotent via offline_client_uuid
     * for checkins, and via booking_id for logouts.
     */
    public function sync(Request $request): JsonResponse
    {
        /** @var Computer|null $computer */
        $computer = $request->attributes->get('kiosk_computer');
        abort_unless($computer, 401);

        $validated = $request->validate([
            'events' => ['required', 'array', 'max:200'],
            'events.*.type' => ['required', 'in:offline_checkin,logout'],
            'events.*.payload' => ['required', 'array'],
        ]);

        $applied = [];
        $rejected = [];

        foreach ($validated['events'] as $event) {
            $type = $event['type'];
            $payload = $event['payload'];

            try {
                if ($type === 'offline_checkin') {
                    $applied[] = $this->applyOfflineCheckin($computer, $payload);
                } elseif ($type === 'logout') {
                    $applied[] = $this->applyOfflineLogout($computer, $payload);
                }
            } catch (\Throwable $e) {
                $rejected[] = ['payload' => $payload, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'ok' => true,
            'applied' => $applied,
            'rejected' => $rejected,
        ]);
    }

    protected function applyOfflineCheckin(Computer $computer, array $p): array
    {
        $uuid = $p['uuid'] ?? null;
        if (! $uuid) {
            throw new \InvalidArgumentException('uuid required');
        }

        $existing = ComputerBooking::where('offline_client_uuid', $uuid)->first();
        if ($existing) {
            return ['type' => 'offline_checkin', 'uuid' => $uuid, 'booking_id' => $existing->id, 'status' => 'duplicate'];
        }

        $startedAt = Carbon::parse($p['started_at']);
        $booking = ComputerBooking::create([
            'user_id' => null,
            'computer_id' => $computer->id,
            'booking_date' => $startedAt->toDateString(),
            'start_time' => $startedAt->format('H:i'),
            'end_time' => $startedAt->copy()->addHour()->format('H:i'),
            'purpose' => $p['purpose'] ?? 'Offline walk-in',
            'status' => ComputerBooking::STATUS_ACTIVE,
            'tnc_accepted_at' => $startedAt,
            'checked_in_at' => $startedAt,
            'actual_started_at' => $startedAt,
            'is_walk_in' => true,
            'is_offline_walkin' => true,
            'offline_walkin_name' => $p['name'] ?? null,
            'offline_client_uuid' => $uuid,
        ]);

        return ['type' => 'offline_checkin', 'uuid' => $uuid, 'booking_id' => $booking->id, 'status' => 'created'];
    }

    protected function applyOfflineLogout(Computer $computer, array $p): array
    {
        $bookingId = $p['booking_id'] ?? null;
        $uuid = $p['uuid'] ?? null;

        $booking = null;
        if ($bookingId) {
            $booking = ComputerBooking::where('id', $bookingId)->where('computer_id', $computer->id)->first();
        }
        if (! $booking && $uuid) {
            $booking = ComputerBooking::where('offline_client_uuid', $uuid)->where('computer_id', $computer->id)->first();
        }

        if (! $booking) {
            throw new \RuntimeException('booking not found');
        }

        if ($booking->status === ComputerBooking::STATUS_COMPLETED && $booking->actual_ended_at) {
            return ['type' => 'logout', 'booking_id' => $booking->id, 'status' => 'duplicate'];
        }

        $endedAt = Carbon::parse($p['ended_at']);
        $booking->actual_ended_at = $endedAt;
        if ($booking->actual_started_at) {
            $booking->actual_duration_seconds = max(0, (int) abs($endedAt->diffInSeconds($booking->actual_started_at)));
        } elseif (isset($p['started_at'])) {
            $started = Carbon::parse($p['started_at']);
            $booking->actual_started_at = $started;
            $booking->actual_duration_seconds = max(0, (int) abs($endedAt->diffInSeconds($started)));
        }
        $booking->status = ComputerBooking::STATUS_COMPLETED;
        $booking->save();

        return ['type' => 'logout', 'booking_id' => $booking->id, 'status' => 'updated'];
    }

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var Computer|null $computer */
        $computer = $request->attributes->get('kiosk_computer');
        abort_unless($computer, 401);

        $validated = $request->validate([
            'app_version' => ['nullable', 'string', 'max:32'],
            'uptime_seconds' => ['nullable', 'integer', 'min:0'],
            'running_apps' => ['nullable', 'array', 'max:50'],
            'running_apps.*' => ['string', 'max:100'],
            'command_acks' => ['nullable', 'array', 'max:20'],
            'command_acks.*.id' => ['required_with:command_acks', 'integer'],
            'command_acks.*.status' => ['required_with:command_acks', 'in:acked,failed'],
            'command_acks.*.error' => ['nullable', 'string', 'max:500'],
        ]);

        $now = now();
        $computer->last_seen_at = $now;
        $computer->last_heartbeat_at = $now;
        $computer->last_heartbeat_data = [
            'app_version' => $validated['app_version'] ?? null,
            'uptime_seconds' => $validated['uptime_seconds'] ?? null,
            'running_apps' => array_values($validated['running_apps'] ?? []),
            'source' => 'electron',
        ];
        $computer->save();

        // Apply command acks first so the kiosk doesn't see the same command again
        // in this same response.
        if (! empty($validated['command_acks'])) {
            $this->applyCommandAcks($computer, $validated['command_acks']);
        }

        // Pull any pending commands and mark them sent. The kiosk acks via the
        // *next* heartbeat. If the kiosk dies mid-execution (e.g. shutdown), the
        // command stays in 'sent' state — admin can re-issue if needed.
        $pendingCommands = KioskCommand::where('computer_id', $computer->id)
            ->where('status', KioskCommand::STATUS_PENDING)
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($pendingCommands->isNotEmpty()) {
            KioskCommand::whereIn('id', $pendingCommands->pluck('id'))
                ->update(['status' => KioskCommand::STATUS_SENT, 'sent_at' => $now]);
        }

        return response()->json([
            'ok' => true,
            'server_time' => $now->toIso8601String(),
            'settings' => [
                'heartbeat_interval' => (int) (Setting::get('computer_kiosk_heartbeat_interval_seconds') ?? 30),
                'latest_app_version' => Setting::get('computer_kiosk_latest_version'),
                'running_apps_whitelist' => $this->whitelist(),
                'admin_pin' => Setting::get('computer_kiosk_admin_pin') ?? '9999',
            ],
            'commands' => $pendingCommands->map(fn (KioskCommand $c) => [
                'id' => $c->id,
                'command' => $c->command,
            ])->values(),
        ]);
    }

    protected function applyCommandAcks(Computer $computer, array $acks): void
    {
        foreach ($acks as $ack) {
            $cmd = KioskCommand::where('id', $ack['id'])
                ->where('computer_id', $computer->id)
                ->first();
            if (! $cmd) {
                continue;
            }
            $cmd->status = $ack['status'] === 'failed' ? KioskCommand::STATUS_FAILED : KioskCommand::STATUS_ACKED;
            $cmd->acked_at = now();
            $cmd->error = $ack['error'] ?? null;
            $cmd->save();
        }
    }

    /**
     * Web-based heartbeat untuk Mac (Safari/Chrome kiosk mode). Slug di URL,
     * no bearer karena sendBeacon tidak reliably set custom headers. Throttled di route.
     */
    public function heartbeatWeb(string $slug, Request $request): JsonResponse
    {
        $computer = Computer::where('checkin_slug', $slug)->first();
        if (! $computer) {
            return response()->json(['error' => 'invalid_slug'], 404);
        }

        $validated = $request->validate([
            'uptime_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $now = now();
        $computer->last_seen_at = $now;
        $computer->last_heartbeat_at = $now;
        $computer->last_heartbeat_data = [
            'app_version' => 'web',
            'uptime_seconds' => $validated['uptime_seconds'] ?? null,
            'running_apps' => [],
            'source' => 'web',
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ];
        $computer->save();

        return response()->json([
            'ok' => true,
            'heartbeat_interval' => (int) (Setting::get('computer_kiosk_heartbeat_interval_seconds') ?? 30),
        ]);
    }

    public function updateManifest()
    {
        $path = storage_path('app/kiosk-releases/latest.yml');
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, ['Content-Type' => 'text/yaml']);
    }

    public function updateFile(string $file): BinaryFileResponse
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.(exe|blockmap|yml)$/', $file)) {
            abort(404);
        }

        $path = storage_path('app/kiosk-releases/'.$file);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    protected function whitelist(): array
    {
        $raw = (string) (Setting::get('computer_kiosk_running_apps_whitelist') ?? '');
        if (trim($raw) === '') {
            return [
                'Adobe Premiere Pro.exe',
                'AfterFX.exe',
                'Photoshop.exe',
                'Illustrator.exe',
                'Resolve.exe',
                'OBS64.exe',
                'obs64.exe',
                'Audacity.exe',
                'Audition.exe',
            ];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw))));
    }
}
