<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\Setting;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AdminPwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $name = Setting::get('pwa_admin_name', 'Warehouse FTV');
        $shortName = Setting::get('pwa_admin_short_name', 'Warehouse FTV');
        $themeColor = Setting::get('pwa_admin_theme_color', '#0ea5e9');
        $bgColor = Setting::get('pwa_admin_background_color', '#ffffff');

        $iconPath = Setting::get('pwa_admin_icon');
        $iconUrl = $iconPath ? asset('storage/' . $iconPath) : asset('favicon.ico');

        $icons = [];
        foreach ([72, 96, 128, 144, 152, 192, 384, 512] as $size) {
            $icons[] = [
                'src' => $iconUrl,
                'sizes' => $size . 'x' . $size,
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ];
        }

        return response()->json([
            'name' => $name,
            'short_name' => $shortName,
            'description' => $name . ' Admin Panel',
            'id' => '/admin',
            'start_url' => '/admin',
            'scope' => '/admin',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => $bgColor,
            'theme_color' => $themeColor,
            'icons' => $icons,
        ], 200, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function serviceWorker(): Response
    {
        $content = view('admin-pwa.service-worker')->render();

        return response($content, 200, [
            'Content-Type' => 'application/javascript',
            'Service-Worker-Allowed' => '/admin',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function subscribe(Request $request, WebPushService $push): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $data = $request->validate([
            'endpoint' => 'required|string|max:2000',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        PushSubscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => $data['endpoint'],
            ],
            [
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'user_agent' => substr((string) $request->header('User-Agent', ''), 0, 255),
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'configured' => $push->isConfigured(),
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $endpoint = $request->input('endpoint');
        if (! $endpoint) {
            return response()->json(['error' => 'endpoint required'], 422);
        }

        PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $endpoint)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function publicKey(WebPushService $push): JsonResponse
    {
        return response()->json([
            'key' => $push->publicKey(),
            'configured' => $push->isConfigured(),
        ]);
    }

    public function test(WebPushService $push): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $push->sendToUser($user->id, [
            'title' => Setting::get('pwa_admin_name', 'Warehouse FTV'),
            'body' => 'Test notification dari admin panel. Push berhasil!',
            'url' => '/admin',
            'tag' => 'test-' . time(),
        ]);

        return response()->json(['ok' => true]);
    }
}
