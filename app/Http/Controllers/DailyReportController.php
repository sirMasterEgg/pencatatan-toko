<?php

namespace App\Http\Controllers;

use App\Services\DailyReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyReportController extends Controller
{
    public function __invoke(Request $request, DailyReportService $service): View
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $date = empty($validated['date']) ? today() : Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay();

        return view('reports.daily', [
            'report' => $service->forDate($date),
            'previousDate' => $date->copy()->subDay(),
            'nextDate' => $date->copy()->addDay(),
            'isToday' => $date->isSameDay(today()),
        ]);
    }
}
