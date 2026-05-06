<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\ComputerBooking;
use App\Models\ComputerBookingSlot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComputerCheckinController extends Controller
{
    public function show(string $slug)
    {
        $computer = Computer::where('checkin_slug', $slug)->with('room')->firstOrFail();

        $today = Carbon::today();
        $now = Carbon::now();

        $todaysBookings = ComputerBooking::with('user:id,name')
            ->where('computer_id', $computer->id)
            ->whereDate('booking_date', $today->toDateString())
            ->orderBy('start_time')
            ->get();

        $currentSession = $this->detectCurrentSession($computer, $now);

        $activeBooking = $todaysBookings->first(function (ComputerBooking $b) use ($now) {
            $start = Carbon::parse($b->booking_date->toDateString().' '.$b->start_time);
            $end = Carbon::parse($b->booking_date->toDateString().' '.$b->end_time);

            return in_array($b->status, [ComputerBooking::STATUS_CONFIRMED, ComputerBooking::STATUS_ACTIVE])
                && $now->between($start, $end);
        });

        return view('frontend.computers.checkin', [
            'computer' => $computer,
            'todaysBookings' => $todaysBookings,
            'activeBooking' => $activeBooking,
            'currentSession' => $currentSession,
            'checkinUrl' => route('kiosk.checkin', $slug),
        ]);
    }

    public function checkin(string $slug, Request $request)
    {
        $computer = Computer::where('checkin_slug', $slug)->firstOrFail();

        if (! Auth::guard('customer')->check()) {
            return redirect()->route('customer.login')
                ->with('intended_url', route('kiosk.checkin', $slug))
                ->withErrors(['login' => 'Silakan login akun warehouse untuk check-in.']);
        }

        $userId = Auth::guard('customer')->id();
        $now = Carbon::now();

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

            return redirect()
                ->route('kiosk.checkin', $slug)
                ->with('success', 'Check-in berhasil. Selamat menggunakan komputer.');
        }

        // Walk-in: auto-create booking for current session if any
        $session = $this->detectCurrentSession($computer, $now);

        if (! $session) {
            return redirect()
                ->route('kiosk.checkin', $slug)
                ->withErrors(['walkin' => 'Saat ini bukan jam operasional, tidak bisa walk-in.']);
        }

        if ($computer->status !== Computer::STATUS_AVAILABLE) {
            return redirect()
                ->route('kiosk.checkin', $slug)
                ->withErrors(['walkin' => 'Komputer sedang tidak tersedia.']);
        }

        // Check the slot is not already taken by someone else
        $taken = ComputerBooking::where('computer_id', $computer->id)
            ->whereIn('status', [ComputerBooking::STATUS_CONFIRMED, ComputerBooking::STATUS_ACTIVE])
            ->whereDate('booking_date', $now->toDateString())
            ->where('start_time', '<', $session['end'])
            ->where('end_time', '>', $session['start'])
            ->exists();

        if ($taken) {
            return redirect()
                ->route('kiosk.checkin', $slug)
                ->withErrors(['walkin' => 'Slot ini sudah terbooking oleh user lain.']);
        }

        $booking = ComputerBooking::create([
            'user_id' => $userId,
            'computer_id' => $computer->id,
            'booking_date' => $now->toDateString(),
            'start_time' => $session['start'],
            'end_time' => $session['end'],
            'purpose' => 'Walk-in check-in',
            'status' => ComputerBooking::STATUS_ACTIVE,
            'tnc_accepted_at' => $now,
            'checked_in_at' => $now,
            'is_walk_in' => true,
        ]);

        return redirect()
            ->route('kiosk.checkin', $slug)
            ->with('success', 'Walk-in check-in berhasil. Slot otomatis dibuat untuk Anda.');
    }

    protected function detectCurrentSession(Computer $computer, Carbon $now): ?array
    {
        $time = $now->format('H:i');

        $slot = ComputerBookingSlot::active()
            ->forDay($now->dayOfWeek)
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
            'is_night' => (bool) $slot->is_night,
        ];
    }
}
