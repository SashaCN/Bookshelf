<?php

namespace App\Http\Controllers;

use App\Services\Stats\ReadingStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $chapters = $statistics->chaptersThisMonthAndYear();

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
            'reader' => [
                'chapters_this_month' => $chapters['month'],
                'chapters_this_year' => $chapters['year'],
                'documents_in_progress' => $statistics->documentsInProgress(),
            ],
        ]]);
    }

    /**
     * Pages per day for a chart: a range (`from`, `to`) or the last `days` days up to today. Without any of them it is
     * the last 30 days. At most a year can be asked for.
     */
    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'days' => ['nullable', 'integer', 'between:1,366'],
        ]);

        $statistics = new ReadingStatistics($request->user());
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : $statistics->today();
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : $to->subDays(((int) ($validated['days'] ?? 30)) - 1);

        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => ['stats.range_invalid']]);
        }

        if ($from->diffInDays($to) > 365) {
            return response()->json([
                'message' => 'The range is too long.',
                'errors' => ['from' => ['stats.range_too_long']],
            ], 422);
        }

        $days = $statistics->dailyPages($from, $to);
        $chapters = $statistics->dailyChapters($from, $to);

        return response()->json([
            'data' => collect($days)->map(fn (int $pages, string $date) => ['date' => $date, 'pages' => $pages, 'chapters' => $chapters[$date]])->values(),
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'total_pages' => array_sum($days)],
        ]);
    }
}
