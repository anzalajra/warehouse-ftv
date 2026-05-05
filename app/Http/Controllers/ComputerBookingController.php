<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\ComputerBooking;
use App\Services\ComputerValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComputerBookingController extends Controller
{
    public function index()
    {
        $bookings = ComputerBooking::with('computer')
            ->where('user_id', Auth::guard('customer')->id())
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->paginate(15);

        return view('frontend.computers.bookings.index', compact('bookings'));
    }

    public function create(Computer $computer)
    {
        abort_if($computer->status !== Computer::STATUS_AVAILABLE, 403, 'Komputer tidak tersedia untuk booking.');

        return view('frontend.computers.bookings.create', [
            'computer' => $computer,
            'tncText' => ComputerValidationService::tncText(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'computer_id' => ['required', 'exists:computers,id'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'purpose' => ['required', 'string', 'min:10', 'max:1000'],
            'tnc' => ['accepted'],
        ]);

        $userId = Auth::guard('customer')->id();
        $date = Carbon::parse($validated['booking_date']);
        $start = $validated['start_time'];
        $end = $validated['end_time'];

        $computer = Computer::findOrFail($validated['computer_id']);
        if ($computer->status !== Computer::STATUS_AVAILABLE) {
            return back()->withInput()->withErrors(['computer_id' => 'Komputer tidak tersedia.']);
        }

        $timeCheck = ComputerValidationService::validateBookingTime($date, $start, $end);
        if (! $timeCheck['ok']) {
            return back()->withInput()->withErrors(['start_time' => $timeCheck['error']]);
        }

        $available = ComputerValidationService::checkComputerAvailability($computer->id, $date, $start, $end);
        if (! $available) {
            return back()->withInput()->withErrors(['start_time' => 'Slot tersebut sudah dibooking atau komputer sedang maintenance.']);
        }

        $duration = (strtotime($end) - strtotime($start)) / 3600;
        $quota = ComputerValidationService::checkUserQuota($userId, $date, $duration);
        if (! $quota['ok']) {
            return back()->withInput()->withErrors(['start_time' => $quota['reason']]);
        }

        $booking = ComputerBooking::create([
            'user_id' => $userId,
            'computer_id' => $computer->id,
            'booking_date' => $date->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'purpose' => $validated['purpose'],
            'status' => ComputerBooking::STATUS_CONFIRMED,
            'tnc_accepted_at' => now(),
        ]);

        return redirect()
            ->route('customer.computer-bookings.show', $booking)
            ->with('success', 'Booking berhasil dibuat.');
    }

    public function show(ComputerBooking $booking)
    {
        $this->authorizeOwnership($booking);

        $booking->load('computer');

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
}
