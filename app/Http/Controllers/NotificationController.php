<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function markAsRead($id)
    {
        $notification = Auth::guard('customer')->user()
            ->customerNotifications()
            ->where('id', $id)
            ->first();

        if ($notification) {
            $notification->markAsRead();
            
            // Redirect to the action URL if available
            if (isset($notification->data['actions'][0]['url'])) {
                return redirect($notification->data['actions'][0]['url']);
            }
            // Fallback for older format or if no action
            if (isset($notification->data['url'])) {
                return redirect($notification->data['url']);
            }
        }

        return back();
    }

    public function markAllAsRead()
    {
        Auth::guard('customer')->user()->unreadCustomerNotifications()->update(['read_at' => now()]);
        return back()->with('success', 'All notifications marked as read.');
    }
}
