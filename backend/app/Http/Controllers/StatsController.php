<?php

namespace App\Http\Controllers;

use App\Services\Stats\ReadingStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /**
     * Everything the statistics screen needs at once: today, streak, pace, totals of the month and the year,
     * and the forecast for each book that is being read.
     */
    public function summary(Request $request): JsonResponse
    {
        $statistics = new ReadingStatistics($request->user());
        $today = $statistics->today();
        $month = $today->startOfMonth();
        $year = $today->startOfYear();

        return response()->json(['data' => [
            'today' => [
                'date' => $today->toDateString(),
                'pages' => $statistics->pagesBetween($today, $today),
            ],
            'streak' => $statistics->streak(),
            'pace' => round($statistics->pace(), 1),
            'month' => [
                'pages' => $statistics->pagesBetween($month, $today),
                'books_finished' => $statistics->booksFinished($month, $today),
            ],
            'year' => [
                'pages' => $statistics->pagesBetween($year, $today),
                'books_finished' => $statistics->booksFinished($year, $today),
            ],
            'forecasts' => $statistics->forecasts(),
        ]]);
    }

    /**
     * Pages per day for a chart. Without a range it is the last 30 days; at most a year can be asked for.
     */
    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $statistics = new ReadingStatistics($request->user());
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : $statistics->today();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $to->subDays(29);

        if ($from->diffInDays($to) > 365) {
            return response()->json([
                'message' => 'The range is too long.',
                'errors' => ['from' => ['stats.range_too_long']],
            ], 422);
        }

        $days = $statistics->dailyPages($from, $to);

        return response()->json([
            'data' => collect($days)->map(fn (int $pages, string $date) => ['date' => $date, 'pages' => $pages])->values(),
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'total_pages' => array_sum($days)],
        ]);
    }
}
