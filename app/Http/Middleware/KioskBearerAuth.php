<?php

namespace App\Http\Middleware;

use App\Models\Computer;
use Closure;
use Illuminate\Http\Request;

class KioskBearerAuth
{
    /** Length of tokens minted by KioskApiController::pair() (Str::random(64)). */
    public const KIOSK_TOKEN_LENGTH = 64;

    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['error' => 'missing_token'], 401);
        }

        $token = substr($header, 7);
        if (strlen($token) !== self::KIOSK_TOKEN_LENGTH) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $computer = Computer::where('kiosk_token', $token)->first();
        if (! $computer) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $request->attributes->set('kiosk_computer', $computer);

        return $next($request);
    }
}
