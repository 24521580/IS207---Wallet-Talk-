<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function __invoke(Request $request): View
    {
        $preset = $request->string('preset')->toString() ?: 'month';
        $range = $this->reportService->resolveRange(
            $preset,
            $request->input('from'),
            $request->input('to'),
        );

        return view('reports.index', [
            'preset' => $preset,
            ...$this->reportService->build($request->user(), $range['from'], $range['to']),
        ]);
    }
}
