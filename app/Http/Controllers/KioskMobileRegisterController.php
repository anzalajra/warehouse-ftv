<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\ComputerBooking;
use App\Models\ComputerBookingSlot;
use App\Models\CustomerCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Mobile-side flow when an unregistered email tries to check in at a kiosk.
 * QR on the kiosk encodes the URL to this controller's `show` route. Student
 * scans on phone, fills minimal registration form, and on submit we both
 * create the user AND perform the check-in/walk-in/override on the target
 * computer. Kiosk page is responsible for polling/refreshing to detect the
 * resulting booking.
 */
class KioskMobileRegisterController extends Controller
{
    public function show(string $slug, Request $request)
    {
        $computer = Computer::where('checkin_slug', $slug)->with('room')->firstOrFail();

        return view('frontend.computers.mobile-kiosk-register', [
            'computer' => $computer,
            'email' => $request->query('email'),
            'purpose' => $request->query('purpose'),
            'sessionIndex' => $request->query('session_index'),
        ]);
    }

    public function register(string $slug, Request $request)
    {
        $computer = Computer::where('checkin_slug', $slug)->firstOrFail();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'purpose' => 'required|string|max:500',
            'session_index' => 'required|integer|min:0',
        ]);

        $now = Carbon::now();
        $session = $this->resolveSession($computer, $now, (int) $data['session_index']);

        if (! $session) {
            return back()->withErrors(['session_index' => 'Sesi tidak valid.']);
        }

        $defaultCategoryId = CustomerCategory::query()->orderBy('id')->value('id');

        $booking = DB::transaction(function () use ($computer, $data, $session, $now, $defaultCategoryId) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'customer_category_id' => $defaultCategoryId,
                'email_verified_at' => $now,
                'is_verified' => true,
                'verified_at' => $now,
            ]);

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
                'purpose' => $data['purpose'],
                'status' => ComputerBooking::STATUS_ACTIVE,
                'tnc_accepted_at' => $now,
                'checked_in_at' => $now,
                'actual_started_at' => $now,
                'is_walk_in' => true,
            ];

            if ($existing && $existing->user_id !== null && $existing->user_id !== $user->id) {
                $existing->status = ComputerBooking::STATUS_OVERRIDDEN;
                $existing->cancelled_reason = 'Diambil alih oleh '.$user->name.' (registrasi baru di kiosk).';
                $existing->save();

                $bookingData['is_override'] = true;
                $bookingData['overrides_booking_id'] = $existing->id;
            }

            return ComputerBooking::create($bookingData);
        });

        return view('frontend.computers.mobile-kiosk-register-success', [
            'computer' => $computer,
            'booking' => $booking,
        ]);
    }

    protected function resolveSession(Computer $computer, Carbon $now, int $index): ?array
    {
        // Mirror ComputerCheckinController::availableSessionsToday
        $current = $this->detectCurrentSession($now) ?? [
            'start' => $now->format('H:i'),
            'end' => $now->copy()->addHour()->format('H:i'),
            'is_night' => false,
        ];

        $sessions = [$current];

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
            ];
        }

        return $sessions[$index] ?? null;
    }

    protected function detectCurrentSession(Carbon $now): ?array
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
