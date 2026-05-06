<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\ComputerBookingSlot;
use App\Models\ComputerRoom;
use App\Services\ComputerValidationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComputerController extends Controller
{
    public function index()
    {
        $rooms = ComputerRoom::active()
            ->withCount(['computers' => function ($q) {
                $q->whereIn('status', [Computer::STATUS_AVAILABLE, Computer::STATUS_MAINTENANCE]);
            }])
            ->orderBy('name')
            ->get();

        return view('frontend.computers.rooms', compact('rooms'));
    }

    public function roomShow(ComputerRoom $room)
    {
        abort_unless($room->is_active, 404);

        $computers = $room->computers()
            ->whereIn('status', [Computer::STATUS_AVAILABLE, Computer::STATUS_MAINTENANCE])
            ->orderBy('name')
            ->get();

        return view('frontend.computers.room-show', compact('room', 'computers'));
    }

    public function show(Computer $computer)
    {
        abort_if($computer->status === Computer::STATUS_RETIRED, 404);

        $computer->load('room');

        return view('frontend.computers.show', compact('computer'));
    }

    public function availability(Computer $computer, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $slotRows = ComputerBookingSlot::active()
            ->forDay($date->dayOfWeek)
            ->orderBy('start_time')
            ->get();

        $slots = [];
        foreach ($slotRows as $slot) {
            $start = $slot->start_time;
            $end = $slot->end_time;
            $available = ComputerValidationService::checkComputerAvailability($computer->id, $date, $start, $end);

            $startsAt = Carbon::parse($date->toDateString().' '.$start);
            if ($startsAt->isPast()) {
                $available = false;
            }

            $slots[] = [
                'start' => $start,
                'end' => $end,
                'available' => $available,
                'is_night' => (bool) $slot->is_night,
            ];
        }

        return response()->json([
            'date' => $date->toDateString(),
            'computer_status' => $computer->status,
            'slots' => $slots,
        ]);
    }
}
