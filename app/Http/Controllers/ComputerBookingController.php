<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\ComputerBooking;
use App\Models\ComputerBookingSlot;
use App\Services\ComputerValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ComputerBookingController extends Controller
{
    public function index()
    {
        $bookings = ComputerBooking::with('computer.room')
            ->where('user_id', Auth::guard('customer')->id())
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->paginate(15);

        return view('frontend.computers.bookings.index', compact('bookings'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'computer_id' => ['required', 'exists:computers,id'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.start' => ['required', 'date_format:H:i'],
            'slots.*.end' => ['required', 'date_format:H:i'],
            'purpose' => ['required', 'string', 'min:1', 'max:5000'],
            'tnc' => ['accepted'],
        ]);

        $userId = Auth::guard('customer')->id();
        $date = Carbon::parse($validated['booking_date']);

        $computer = Computer::findOrFail($validated['computer_id']);
        if ($computer->status !== Computer::STATUS_AVAILABLE) {
            return back()->withInput()->withErrors(['computer_id' => 'Komputer tidak tersedia.']);
        }

        // Sort & validate each slot exists in operational schedule
        $slots = collect($validated['slots'])
            ->map(fn ($s) => ['start' => $s['start'], 'end' => $s['end']])
            ->sortBy('start')
            ->values()
            ->all();

        foreach ($slots as $s) {
            if ($s['start'] >= $s['end']) {
                return back()->withInput()->withErrors(['slots' => 'Waktu mulai harus sebelum selesai.']);
            }
            $check = ComputerValidationService::validateBookingTime($date, $s['start'], $s['end']);
            if (! $check['ok']) {
                return back()->withInput()->withErrors(['slots' => $check['error']]);
            }
        }

        // Detect night-shift requirement
        $hasNight = ComputerBookingSlot::active()
            ->forDay($date->dayOfWeek)
            ->where('is_night', true)
            ->where(function ($q) use ($slots) {
                foreach ($slots as $s) {
                    $q->orWhere(function ($qq) use ($s) {
                        $qq->where('start_time', '<=', $s['start'])->where('end_time', '>=', $s['end']);
                    });
                }
            })
            ->exists();

        if ($hasNight && ! $request->boolean('permit')) {
            return back()->withInput()->withErrors(['permit' => 'Anda harus mengkonfirmasi perizinan menginap untuk slot jam malam.']);
        }

        // Merge contiguous slots into one booking; non-contiguous become separate bookings
        $merged = $this->mergeContiguous($slots);

        // Check availability + quota for each merged window
        $totalDuration = 0;
        foreach ($merged as $window) {
            $totalDuration += (strtotime($window['end']) - strtotime($window['start'])) / 3600;

            $available = ComputerValidationService::checkComputerAvailability(
                $computer->id,
                $date,
                $window['start'],
                $window['end'],
            );
            if (! $available) {
                return back()->withInput()->withErrors(['slots' => "Slot {$window['start']}-{$window['end']} sudah dibooking atau komputer maintenance."]);
            }
        }

        $quota = ComputerValidationService::checkUserQuota($userId, $date, $totalDuration);
        if (! $quota['ok']) {
            return back()->withInput()->withErrors(['slots' => $quota['reason']]);
        }

        // Persist
        $created = DB::transaction(function () use ($merged, $userId, $computer, $date, $validated, $hasNight) {
            $bookings = [];
            foreach ($merged as $window) {
                $bookings[] = ComputerBooking::create([
                    'user_id' => $userId,
                    'computer_id' => $computer->id,
                    'booking_date' => $date->toDateString(),
                    'start_time' => $window['start'],
                    'end_time' => $window['end'],
                    'purpose' => $validated['purpose'],
                    'status' => ComputerBooking::STATUS_CONFIRMED,
                    'tnc_accepted_at' => now(),
                    'permit_acknowledged_at' => $hasNight ? now() : null,
                ]);
            }

            return $bookings;
        });

        $first = $created[0];

        return redirect()
            ->route('customer.computer-bookings.show', $first)
            ->with('success', count($created) > 1
                ? 'Booking berhasil dibuat ('.count($created).' rentang waktu).'
                : 'Booking berhasil dibuat.');
    }

    public function show(ComputerBooking $booking)
    {
        $this->authorizeOwnership($booking);

        $booking->load('computer.room');

        return view('frontend.computers.bookings.show', compact('booking'));
    }

    public function cancel(ComputerBooking $booking)
    {
        $this->authorizeOwnership($booking);

        if (! $booking->isCancellable()) {
            return back()->withErrors(['booking' => 'Booking tidak bisa dibatalkan (sudah berjalan atau dalam status lain).']);
        }

        $booking->status = ComputerBooking::STATUS_CANCELLED;
        $booking->cancelled_reason = 'Cancelled by customer';
        $booking->save();

        return back()->with('success', 'Booking dibatalkan.');
    }

    protected function authorizeOwnership(ComputerBooking $booking): void
    {
        abort_if($booking->user_id !== Auth::guard('customer')->id(), 403);
    }

    /**
     * Merge contiguous time ranges. Each $slots item: ['start' => 'HH:MM', 'end' => 'HH:MM'].
     * Assumes input is sorted by start.
     */
    protected function mergeContiguous(array $slots): array
    {
        if (empty($slots)) {
            return [];
        }

        $merged = [];
        $current = $slots[0];

        for ($i = 1; $i < count($slots); $i++) {
            $next = $slots[$i];
            if ($current['end'] === $next['start']) {
                $current['end'] = $next['end'];
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }

        $merged[] = $current;

        return $merged;
    }
}
