<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\KioskPairingCode;
use App\Models\Setting;
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
        ]);
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

        return response()->json([
            'ok' => true,
            'server_time' => $now->toIso8601String(),
            'settings' => [
                'heartbeat_interval' => (int) (Setting::get('computer_kiosk_heartbeat_interval_seconds') ?? 30),
                'latest_app_version' => Setting::get('computer_kiosk_latest_version'),
                'running_apps_whitelist' => $this->whitelist(),
            ],
        ]);
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
