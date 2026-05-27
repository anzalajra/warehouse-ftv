<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImpersonateController extends Controller
{
    public function start(Request $request, User $user)
    {
        $admin = Auth::guard('web')->user();

        if (!$admin) {
            abort(403, 'Admin authentication required.');
        }

        if ($admin->id === $user->id) {
            return redirect()->back()->with('error', 'You cannot impersonate yourself.');
        }

        $request->session()->put('impersonator_id', $admin->id);

        Auth::guard('customer')->login($user);

        Log::channel(config('logging.default'))->warning('Impersonation started', [
            'admin_id'    => $admin->id,
            'admin_email' => $admin->email,
            'target_id'   => $user->id,
            'target_email'=> $user->email,
            'ip'          => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 255),
            'at'          => now()->toIso8601String(),
        ]);

        return redirect()->route('customer.dashboard')
            ->with('status', 'You are now impersonating ' . $user->name . '.');
    }

    public function stop(Request $request)
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        $targetId = Auth::guard('customer')->id();

        Auth::guard('customer')->logout();

        if ($impersonatorId) {
            Log::channel(config('logging.default'))->warning('Impersonation stopped', [
                'admin_id'  => $impersonatorId,
                'target_id' => $targetId,
                'ip'        => $request->ip(),
                'at'        => now()->toIso8601String(),
            ]);

            return redirect('/admin');
        }

        return redirect('/');
    }
}
