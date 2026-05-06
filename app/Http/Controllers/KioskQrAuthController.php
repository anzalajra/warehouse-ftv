<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\ComputerBooking;
use App\Models\ComputerBookingSlot;
use App\Models\KioskLoginToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KioskQrAuthController extends Controller
{
    /**
     * Kiosk asks server for a fresh short-lived token. The QR encodes the URL
     * /m/kiosk-login/{token} which the student opens on their phone.
     */
    public function issueToken(string $slug): JsonResponse
    {
        $computer = Computer::where('checkin_slug', $slug)->first();
        if (! $computer) {
            return response()->json(['error' => 'invalid_slug'], 404);
        }

        // Cleanup tokens lama untuk computer ini agar tabel tidak gemuk
        KioskLoginToken::where('computer_id', $computer->id)
            ->where(function ($q) {
                $q->where('expires_at', '<', now()->subMinutes(5))
                    ->orWhereNotNull('claimed_at');
            })
            ->delete();

        $token = KioskLoginToken::generateFor($computer->id, 60);

        return response()->json([
            'token' => $token->token,
            'qr_url' => route('mobile.kiosk-login', $token->token),
            'expires_in' => 60,
        ]);
    }

    /**
     * Kiosk polls this to detect when the token has been claimed.
     */
    public function pollToken(string $slug, string $token): JsonResponse
    {
        $entry = KioskLoginToken::with('claimedBy:id,name')
            ->where('token', $token)
            ->whereHas('computer', fn ($q) => $q->where('checkin_slug', $slug))
            ->first();

        if (! $entry) {
            return response()->json(['status' => 'invalid'], 404);
        }

        if ($entry->isClaimed()) {
            return response()->json([
                'status' => 'claimed',
                'user_name' => $entry->claimedBy?->name,
            ]);
        }

        if ($entry->isExpired()) {
            return response()->json(['status' => 'expired']);
        }

        return response()->json(['status' => 'pending']);
    }

    /**
     * Mobile page: student opens this URL on their phone after scanning QR.
     * Requires customer login. Shows confirmation; on confirm, claims token
     * and triggers check-in (or walk-in booking) on the kiosk computer.
     */
    public function showMobileClaim(string $token)
    {
        $entry = KioskLoginToken::with('computer.room')->where('token', $token)->first();

        if (! $entry || $entry->isExpired() || $entry->isClaimed()) {
            return view('frontend.computers.kiosk-qr-status', [
                'state' => $entry?->isClaimed() ? 'already_claimed' : 'expired',
            ]);
        }

        if (! Auth::guard('customer')->check()) {
            session(['url.intended' => route('mobile.kiosk-login', $token)]);

            return redirect()->route('customer.login')
                ->with('info', 'Silakan login untuk melanjutkan check-in di komputer lab.');
        }

        return view('frontend.computers.kiosk-qr-claim', [
            'token' => $entry,
        ]);
    }

    /**
     * Mobile claim submit: student confirms, server marks token claimed and
     * does the check-in or walk-in booking server-side. Kiosk page polling
     * picks up the claim and refreshes.
     */
    public function claimMobile(string $token, Request $request)
    {
        if (! Auth::guard('customer')->check()) {
            return redirect()->route('customer.login');
        }

        $entry = KioskLoginToken::with('computer')->where('token', $token)->first();

        if (! $entry || $entry->isExpired() || $entry->isClaimed()) {
            return view('frontend.computers.kiosk-qr-status', [
                'state' => $entry?->isClaimed() ? 'already_claimed' : 'expired',
            ]);
        }

        $computer = $entry->computer;
        $userId = Auth::guard('customer')->id();
        $now = Carbon::now();

        DB::transaction(function () use ($entry, $computer, $userId, $now) {
            $entry->claimed_by_user_id = $userId;
            $entry->claimed_at = $now;
            $entry->save();

            // Check-in / walk-in flow (mirror ComputerCheckinController::checkin)
            $booking = ComputerBooking::where('computer_id', $computer->id)
                ->where('user_id', $userId)
                ->whereDate('booking_date', $now->toDateString())
                ->whereIn('status', [ComputerBooking::STATUS_CONFIRMED, ComputerBooking::STATUS_ACTIVE])
                ->get()
                ->first(function (ComputerBooking $b) use ($now) {
                    $start = Carbon::parse($b->booking_date->toDateString().' '.$b->start_time);
                    $end = Carbon::parse($b->booking_date->toDateString().' '.$b->end_time);

                    return $now->between($start, $end);
                });

            if ($booking) {
                if (! $booking->checked_in_at) {
                    $booking->checked_in_at = $now;
                    $booking->status = ComputerBooking::STATUS_ACTIVE;
                    $booking->save();
                }

                return;
            }

            if ($computer->status !== Computer::STATUS_AVAILABLE) {
                return; // skip walk-in jika maintenance
            }

            $session = $this->detectCurrentSession($computer, $now);
            if (! $session) {
                return;
            }

            $taken = ComputerBooking::where('computer_id', $computer->id)
                ->whereIn('status', [ComputerBooking::STATUS_CONFIRMED, ComputerBooking::STATUS_ACTIVE])
                ->whereDate('booking_date', $now->toDateString())
                ->where('start_time', '<', $session['end'])
                ->where('end_time', '>', $session['start'])
                ->exists();

            if ($taken) {
                return;
            }

            ComputerBooking::create([
                'user_id' => $userId,
                'computer_id' => $computer->id,
                'booking_date' => $now->toDateString(),
                'start_time' => $session['start'],
                'end_time' => $session['end'],
                'purpose' => 'Walk-in via QR check-in',
                'status' => ComputerBooking::STATUS_ACTIVE,
                'tnc_accepted_at' => $now,
                'checked_in_at' => $now,
                'is_walk_in' => true,
            ]);
        });

        return view('frontend.computers.kiosk-qr-status', [
            'state' => 'success',
            'computer' => $computer,
        ]);
    }

    protected function detectCurrentSession(Computer $computer, Carbon $now): ?array
    {
        $time = $now->format('H:i');

        $slot = ComputerBookingSlot::where('is_active', true)
            ->where('day_of_week', $now->dayOfWeek)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->orderBy('start_time')
            ->first();

        if (! $slot) {
            return null;
        }

        return [
            'start' => $slot->start_time,
            'end' => $slot->end_time,
        ];
    }
}
