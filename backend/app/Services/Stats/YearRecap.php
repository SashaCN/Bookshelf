<?php

namespace App\Services\Stats;

use App\Enums\PostType;
use App\Models\Post;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;
use Carbon\CarbonImmutable;

/**
 * "A year in books": what a reader did in one calendar year of their own time zone. Counting rules (passes of a
 * reread, net pages of a day, the reader's calendar) are the ones of ReadingStatistics.
 */
final class YearRecap
{
    /** The earliest year a recap can be asked for: the reader's first activity (the current year for a new reader). */
    public function firstYear(User $user): int
    {
        $statistics = new ReadingStatistics($user);

        return $statistics->firstActivityYear() ?? $statistics->today()->year;
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, int $year): array
    {
        $statistics = new ReadingStatistics($user);
        $timezone = $user->timezone;
        $first = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone);
        $last = $first->endOfYear();
        $utc = [$first->utc(), $last->utc()];

        $days = $statistics->dailyPages($first, $last);
        $readingDays = array_keys(array_filter($days, fn (int $pages) => $pages > 0));

        $passes = $statistics->finishedPasses($first, $last);
        $books = UserBook::query()
            ->whereIn('id', array_values(array_unique(array_column($passes, 'user_book_id'))))
            ->with('book.authors')
            ->get()
            ->keyBy('id');

        $finished = array_map(function (array $pass) use ($books, $timezone): array {
            $entry = $books[$pass['user_book_id']];

            return [
                'user_book_id' => $entry->id,
                'title' => $entry->book->title,
                'authors' => $entry->book->authors->pluck('name')->all(),
                'finished_at' => $pass['finished_at']->setTimezone($timezone)->toDateString(),
                'rating' => $entry->rating,
                'total_pages' => $entry->total_pages,
            ];
        }, $passes);

        $rated = array_values(array_filter($finished, fn (array $pass) => $pass['rating'] !== null));

        // A book read twice in a year counts twice in the totals, but shows once among the best.
        $topRated = $rated;
        usort($topRated, fn (array $a, array $b) => [$b['rating'], $a['finished_at']] <=> [$a['rating'], $b['finished_at']]);
        $topRated = collect($topRated)->unique('user_book_id')->take(3)->values()->map(
            fn (array $pass) => ['title' => $pass['title'], 'authors' => $pass['authors'], 'rating' => $pass['rating'], 'finished_at' => $pass['finished_at']],
        )->all();

        $longest = collect($finished)->whereNotNull('total_pages')->sortBy([['total_pages', 'desc'], ['finished_at', 'asc']])->first();

        $target = $user->readingGoals()->where('year', $year)->value('target_books');

        return [
            'year' => $year,
            'books_finished' => count($passes),
            'pages_read' => array_sum($days),
            'days_with_reading' => count($readingDays),
            'longest_streak_in_year' => ReadingStatistics::longestRun($readingDays),
            'quotes_added' => Quote::query()->where('user_id', $user->id)->whereBetween('created_at', $utc)->count(),
            // A review is a post of the reader about a book; one per book, so this counts books reviewed in the year.
            'reviews_written' => Post::query()->where('user_id', $user->id)->where('type', PostType::Review)->whereBetween('created_at', $utc)->count(),
            'average_rating' => $rated === [] ? null : round(array_sum(array_column($rated, 'rating')) / count($rated), 1),
            'top_rated' => $topRated,
            'longest_book' => $longest === null ? null : ['title' => $longest['title'], 'authors' => $longest['authors'], 'total_pages' => $longest['total_pages']],
            'busiest_month' => $this->busiestMonth($days),
            'goal' => $target === null ? null : ['target' => (int) $target, 'achieved' => count($passes)],
            'finished_books' => array_map(
                fn (array $pass) => ['title' => $pass['title'], 'authors' => $pass['authors'], 'finished_at' => $pass['finished_at'], 'rating' => $pass['rating']],
                $finished,
            ),
        ];
    }

    /**
     * The month with the most pages (the earlier one on a tie), or null when nothing was read.
     *
     * @param  array<string, int>  $days  day (YYYY-MM-DD) => pages
     * @return array{month: int, pages: int}|null
     */
    private function busiestMonth(array $days): ?array
    {
        $months = [];
        foreach ($days as $day => $pages) {
            $month = (int) substr($day, 5, 2);
            $months[$month] = ($months[$month] ?? 0) + $pages;
        }
        $months = array_filter($months);

        if ($months === []) {
            return null;
        }

        $best = array_key_first($months);
        foreach ($months as $month => $pages) {
            if ($pages > $months[$best]) {
                $best = $month;
            }
        }

        return ['month' => $best, 'pages' => $months[$best]];
    }
}
