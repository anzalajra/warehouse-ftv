<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\ComputerBooking;
use App\Models\ComputerBookingSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // If a booking is already checked in (e.g. mobile-register auto-checkin
        // just finished), jump straight to the floating timer page.
        if ($activeBooking
            && $activeBooking->status === ComputerBooking::STATUS_ACTIVE
            && $activeBooking->checked_in_at) {
            return redirect()
                ->route('kiosk.timer', $slug)
                ->with('booking_id', $activeBooking->id);
        }

        $nextSessionBooking = null;
        if ($activeBooking) {
            $nextSessionBooking = $todaysBookings->first(function (ComputerBooking $b) use ($activeBooking) {
                return in_array($b->status, [ComputerBooking::STATUS_CONFIRMED, ComputerBooking::STATUS_ACTIVE])
                    && $b->id !== $activeBooking->id
                    && $b->start_time >= $activeBooking->end_time;
            });
        }

        $isLate = false;
        if ($activeBooking && ! $activeBooking->checked_in_at) {
            $start = Carbon::parse($activeBooking->booking_date->toDateString().' '.$activeBooking->start_time);
            $isLate = $now->diffInMinutes($start, false) < -10;
        }

        return view('frontend.computers.checkin', [
            'computer' => $computer,
            'todaysBookings' => $todaysBookings,
            'activeBooking' => $activeBooking,
            'nextSessionBooking' => $nextSessionBooking,
            'currentSession' => $currentSession,
            'isLate' => $isLate,
            'checkinUrl' => route('kiosk.checkin', $slug),
        ]);
    }

    /**
     * Direct check-in: original booking owner uses this from the kiosk.
     */
    public function checkin(string $slug, Request $request)
    {
        $computer = Computer::where('checkin_slug', $slug)->firstOrFail();
        $now = Carbon::now();

        $activeBooking = $this->findActiveBooking($computer, $now);

        if ($activeBooking) {
            // Booking exists for current slot — owner clicks check-in.
            // We assume the user at the kiosk IS the booking owner here.
            // (Identity verification happens via the override flow if not.)
            DB::transaction(function () use ($activeBooking, $now) {
                if (! $activeBooking->checked_in_at) {
                    $activeBooking->checked_in_at = $now;
                    $activeBooking->status = ComputerBooking::STATUS_ACTIVE;
                    $activeBooking->actual_started_at = $now;
                    $activeBooking->save();
                }
            });

            return redirect()
                ->route('kiosk.timer', $slug)
                ->with('booking_id', $activeBooking->id);
        }

        // No active booking → walk-in flow needs an authenticated email.
        // For walk-in without an existing booking, redirect to the override form
        // which handles email lookup + registration.
        return redirect()->route('kiosk.checkin.other', $slug);
    }

    /**
     * Show the "Orang lain check-in" form (also used for walk-in without booking).
     */
    public function showOther(string $slug)
    {
        $computer = Computer::where('checkin_slug', $slug)->with('room')->firstOrFail();
        $now = Carbon::now();

        $activeBooking = $this->findActiveBooking($computer, $now);

        $sessions = $this->availableSessionsToday($computer, $now);

        return view('frontend.computers.checkin-other', [
            'computer' => $computer,
            'activeBooking' => $activeBooking,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Submit "Orang lain check-in" — verify email, override or walk-in.
     */
    public function submitOther(string $slug, Request $request)
    {
        $computer = Computer::where('checkin_slug', $slug)->firstOrFail();

        $data = $request->validate([
            'email' => 'required|email',
            'session_index' => 'required|integer|min:0',
            'purpose' => 'required|string|max:500',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            // Render registration QR page
            return redirect()->route('kiosk.checkin.register', $slug)
                ->with('email', $data['email'])
                ->with('purpose', $data['purpose'])
                ->with('session_index', $data['session_index']);
        }

        $now = Carbon::now();
        $sessions = $this->availableSessionsToday($computer, $now);
        $session = $sessions[$data['session_index']] ?? null;

        if (! $session) {
            return back()->withErrors(['session_index' => 'Sesi tidak valid.']);
        }

        $booking = $this->performOverrideOrWalkIn($computer, $user, $session, $data['purpose'], $now);

        return redirect()
            ->route('kiosk.timer', $slug)
            ->with('booking_id', $booking->id);
    }

    /**
     * Perform the override/walk-in transaction. If a booking exists in the slot,
     * it gets marked overridden. New booking row created for the new user.
     */
    protected function performOverrideOrWalkIn(Computer $computer, User $user, array $session, string $purpose, Carbon $now): ComputerBooking
    {
        return DB::transaction(function () use ($computer, $user, $session, $purpose, $now) {
            $existing = ComputerBooking::where('computer_id', $computer->id)
                ->whereDate('booking_date', $now->toDateString())
                ->whereIn('status', [ComputerBooking::STATUS_CONFIRMED, ComputerBooking::STATUS_ACTIVE])
                ->where('start_time', '<', $session['end'])
                ->where('end_time', '>', $session['start'])
                ->first();

            $bookingData = [
                'user_id' => $user->id,
                'computer_id' => $computer->id,
                'booking_date' => $now->toDateString(),
                'start_time' => $session['start'],
                'end_time' => $session['end'],
                'purpose' => $purpose,
                'status' => ComputerBooking::STATUS_ACTIVE,
                'tnc_accepted_at' => $now,
                'checked_in_at' => $now,
                'actual_started_at' => $now,
                'is_walk_in' => true,
            ];

            if ($existing && $existing->user_id !== null && $existing->user_id !== $user->id) {
                $existing->status = ComputerBooking::STATUS_OVERRIDDEN;
                $existing->cancelled_reason = 'Diambil alih oleh '.$user->name.' di kiosk.';
                $existing->save();

                $bookingData['is_override'] = true;
                $bookingData['overrides_booking_id'] = $existing->id;
            } elseif ($existing && $existing->user_id === $user->id) {
                // Same user — just check in
                if (! $existing->checked_in_at) {
                    $existing->checked_in_at = $now;
                    $existing->status = ComputerBooking::STATUS_ACTIVE;
                    $existing->actual_started_at = $now;
                    $existing->save();
                }

                return $existing;
            }

            return ComputerBooking::create($bookingData);
        });
    }

    /**
     * Show registration QR page (when email not registered).
     */
    public function showRegister(string $slug)
    {
        $computer = Computer::where('checkin_slug', $slug)->with('room')->firstOrFail();

        $email = session('email');
        $purpose = session('purpose');
        $sessionIndex = session('session_index');

        if (! $email) {
            return redirect()->route('kiosk.checkin.other', $slug);
        }

        // Forward state via query string to mobile register URL so post-register can checkin
        $registerUrl = route('mobile.kiosk-register', [
            'slug' => $slug,
            'email' => $email,
            'purpose' => $purpose,
            'session_index' => $sessionIndex,
        ]);

        return view('frontend.computers.checkin-register', [
            'computer' => $computer,
            'email' => $email,
            'registerUrl' => $registerUrl,
        ]);
    }

    /**
     * JSON status endpoint used by the registration-QR kiosk page to poll
     * whether an active booking now exists on this computer (because the
     * student finished mobile registration). Returns booking_id when ready.
     */
    public function status(string $slug)
    {
        $computer = Computer::where('checkin_slug', $slug)->firstOrFail();
        $now = Carbon::now();

        $active = $this->findActiveBooking($computer, $now);
        $hasCheckedIn = $active && $active->checked_in_at && $active->status === ComputerBooking::STATUS_ACTIVE;

        return response()->json([
            'has_active_booking' => $hasCheckedIn,
            'booking_id' => $hasCheckedIn ? $active->id : null,
        ]);
    }

    /**
     * Timer page after successful check-in. Booking ID passed via session flash.
     */
    public function timer(string $slug, Request $request)
    {
        $computer = Computer::where('checkin_slug', $slug)->with('room')->firstOrFail();
        $bookingId = $request->session()->get('booking_id') ?? $request->query('booking');

        $booking = $bookingId
            ? ComputerBooking::with('user:id,name')->find($bookingId)
            : null;

        if (! $booking || $booking->computer_id !== $computer->id) {
            return redirect()->route('kiosk.checkin', $slug);
        }

        return view('frontend.computers.checkin-timer', [
            'computer' => $computer,
            'booking' => $booking,
        ]);
    }

    /**
     * Logout / end session. Computes duration, marks booking completed.
     */
    public function logout(string $slug, Request $request)
    {
        $bookingId = $request->input('booking_id');
        $booking = ComputerBooking::find($bookingId);

        if (! $booking) {
            return redirect()->route('kiosk.checkin', $slug);
        }

        $now = Carbon::now();
        $booking->actual_ended_at = $now;
        if ($booking->actual_started_at) {
            // Carbon 3 returns a signed float; cast to non-negative int for the unsigned column.
            $booking->actual_duration_seconds = max(0, (int) abs($now->diffInSeconds($booking->actual_started_at)));
        }
        $booking->status = ComputerBooking::STATUS_COMPLETED;
        $booking->save();

        return redirect()->route('kiosk.checkin', $slug);
    }

    protected function findActiveBooking(Computer $computer, Carbon $now): ?ComputerBooking
    {
        return ComputerBooking::where('computer_id', $computer->id)
            ->whereDate('booking_date', $now->toDateString())
            ->whereIn('status', [ComputerBooking::STATUS_CONFIRMED, ComputerBooking::STATUS_ACTIVE])
            ->get()
            ->first(function (ComputerBooking $b) use ($now) {
                $start = Carbon::parse($b->booking_date->toDateString().' '.$b->start_time);
                $end = Carbon::parse($b->booking_date->toDateString().' '.$b->end_time);

                return $now->between($start, $end);
            });
    }

    /**
     * Sessions selectable for "orang lain checkin": current + remaining today.
     */
    protected function availableSessionsToday(Computer $computer, Carbon $now): array
    {
        $sessions = [];

        $current = $this->detectCurrentSession($computer, $now) ?? $this->fallbackSession($now);
        $sessions[] = $current + ['label' => 'Sesi sekarang ('.$current['start'].' - '.$current['end'].')'];

        $futureSlots = ComputerBookingSlot::active()
            ->forDay($now->dayOfWeek)
            ->where('start_time', '>', $current['end'])
            ->orderBy('start_time')
            ->get();

        foreach ($futureSlots as $slot) {
            $sessions[] = [
                'start' => $slot->start_time,
                'end' => $slot->end_time,
                'is_night' => (bool) $slot->is_night,
                'label' => $slot->start_time.' - '.$slot->end_time.($slot->is_night ? ' (Malam)' : ''),
            ];
        }

        return $sessions;
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

    protected function fallbackSession(Carbon $now): array
    {
        $start = $now->copy()->format('H:i');
        $end = $now->copy()->addHour()->format('H:i');

        if ($end < $start) {
            $end = '23:59';
        }

        return [
            'start' => $start,
            'end' => $end,
            'is_night' => false,
        ];
    }
}
