<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\DailyReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DailyReportService $service): View
    {
        return view('dashboard', [
            'report' => $service->forDate(today()),
            'needsReviewCount' => Product::where('status', ProductStatus::NeedsReview)->count(),
        ]);
    }
}
