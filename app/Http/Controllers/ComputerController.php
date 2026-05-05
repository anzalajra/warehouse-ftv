<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Services\ComputerValidationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComputerController extends Controller
{
    public function index()
    {
        $computers = Computer::query()
            ->whereIn('status', [Computer::STATUS_AVAILABLE, Computer::STATUS_MAINTENANCE])
            ->orderBy('name')
            ->get();

        return view('frontend.computers.index', compact('computers'));
    }

    public function show(Computer $computer)
    {
        abort_if($computer->status === Computer::STATUS_RETIRED, 404);

        return view('frontend.computers.show', compact('computer'));
    }

    public function availability(Computer $computer, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $slots = ComputerValidationService::getAvailableSlots($computer->id, $date);

        return response()->json([
            'date' => $date->toDateString(),
            'computer_status' => $computer->status,
            'slots' => $slots,
        ]);
    }
}
