<?php

namespace App\Services\Stats;

use App\Enums\BookStatus;
use App\Models\ReadingLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Reading statistics of one reader. Everything is counted in the reader's own time zone
 * (see docs/mvp-plan.md, "Статистика: як рахуємо").
 */
final class ReadingStatistics
{
    /** How many days (today included) the reading pace looks back. */
    public const PACE_DAYS = 14;

    /** A book needs reading on this many different days before its own pace is trusted. */
    private const MIN_DAYS_FOR_BOOK_PACE = 3;

    /** A forecast further away than this is noise rather than information. */
    private const MAX_FORECAST_DAYS = 730;

    /** Pages gained by a log entry; the columns are unsigned, so they are cast before they are subtracted. */
    private const NET_PAGES = 'SUM(CAST(to_page AS SIGNED) - CAST(from_page AS SIGNED))';

    public function __construct(private readonly User $user) {}

    /** The current calendar day of the reader, at midnight. */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->user->timezone)->startOfDay();
    }

    /**
     * Pages read on each day of the range, every day present. The pages of a day are the net sum of its log entries,
     * so a mistyped page and its correction cancel out, and a day never counts below zero.
     *
     * @return array<string, int> day (YYYY-MM-DD) => pages
     */
    public function dailyPages(CarbonInterface $from, CarbonInterface $to): array
    {
        $net = ReadingLog::query()->toBase()
            ->where('user_id', $this->user->id)
            ->whereBetween('logged_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('logged_on')
            ->selectRaw('logged_on, '.self::NET_PAGES.' AS pages')
            ->pluck('pages', 'logged_on');

        $days = [];
        $last = $to->toDateString();

        // Calendar days are compared as text: as moments, midnight in UTC is later than midnight in Kyiv.
        for ($day = CarbonImmutable::parse($from->toDateString(), 'UTC'); $day->toDateString() <= $last; $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[$key] = max(0, (int) ($net[$key] ?? 0));
        }

        return $days;
    }

    public function pagesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return array_sum($this->dailyPages($from, $to));
    }

    /**
     * Days in a row with something read. A streak that ended yesterday is still alive: the day is not over yet.
     *
     * @return array{current: int, longest: int, read_today: bool}
     */
    public function streak(): array
    {
        /** @var list<string> $readingDays */
        $readingDays = ReadingLog::query()->toBase()
            ->where('user_id', $this->user->id)
            ->groupBy('logged_on')
            ->havingRaw(self::NET_PAGES.' > 0')
            ->orderBy('logged_on')
            ->pluck('logged_on')
            ->all();

        $longest = 0;
        $run = 0;
        $previous = null;

        foreach ($readingDays as $day) {
            $run = $previous !== null && CarbonImmutable::parse($previous)->addDay()->toDateString() === $day ? $run + 1 : 1;
            $longest = max($longest, $run);
            $previous = $day;
        }

        $read = array_flip($readingDays);
        $today = $this->today();
        $cursor = isset($read[$today->toDateString()]) ? $today : $today->subDay();

        $current = 0;
        while (isset($read[$cursor->toDateString()])) {
            $current++;
            $cursor = $cursor->subDay();
        }

        return [
            'current' => $current,
            'longest' => $longest,
            'read_today' => isset($read[$today->toDateString()]),
        ];
    }

    /**
     * Pages per day over the last two weeks. Days without reading count too, or a pace would only
     * ever describe the good days.
     */
    public function pace(): float
    {
        $today = $this->today();

        return $this->pagesBetween($today->subDays(self::PACE_DAYS - 1), $today) / self::PACE_DAYS;
    }

    /**
     * Whole days from one calendar day to a later one (YYYY-MM-DD). Counted on plain dates: between moments in the
     * reader's time zone a day can be 23 or 25 hours long.
     */
    private static function daysBetween(string $from, string $to): int
    {
        return (int) abs(CarbonImmutable::parse($from, 'UTC')->diffInDays(CarbonImmutable::parse($to, 'UTC')));
    }

    /**
     * Books finished on the days of the range (inclusive), by the finish date the reader has.
     */
    public function booksFinished(CarbonInterface $from, CarbonInterface $to): int
    {
        $timezone = $this->user->timezone;

        // The dates are stored in UTC, while the range is in the reader's calendar.
        return $this->user->userBooks()
            ->where('status', BookStatus::Finished)
            ->whereBetween('finished_at', [
                CarbonImmutable::parse($from->toDateString(), $timezone)->startOfDay()->utc(),
                CarbonImmutable::parse($to->toDateString(), $timezone)->endOfDay()->utc(),
            ])
            ->count();
    }

    /**
     * When each book that is being read will be finished at the current pace. A book uses its own pace once it has
     * been read on a few different days, and the overall pace until then.
     *
     * @return list<array{user_book_id: int, title: string, current_page: int, total_pages: int, pages_per_day: float|null, days_left: int|null, finish_on: string|null}>
     */
    public function forecasts(): array
    {
        $books = $this->user->userBooks()
            ->where('status', BookStatus::Reading)
            ->whereNotNull('total_pages')
            ->with('book')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        if ($books->isEmpty()) {
            return [];
        }

        $today = $this->today();
        $windowStart = $today->subDays(self::PACE_DAYS - 1);
        $overallPace = $this->pace();

        // Net pages of every book per day of the window, in one query.
        $perBookDay = ReadingLog::query()->toBase()
            ->where('user_id', $this->user->id)
            ->whereBetween('logged_on', [$windowStart->toDateString(), $today->toDateString()])
            ->groupBy('user_book_id', 'logged_on')
            ->selectRaw('user_book_id, logged_on, '.self::NET_PAGES.' AS pages')
            ->get()
            ->groupBy('user_book_id');

        return $books->map(function ($userBook) use ($perBookDay, $overallPace, $today, $windowStart): array {
            $days = ($perBookDay[$userBook->id] ?? collect())
                ->map(fn ($row) => max(0, (int) $row->pages))
                ->filter(fn (int $pages) => $pages > 0);

            $pace = $overallPace;

            if ($days->count() >= self::MIN_DAYS_FOR_BOOK_PACE) {
                // Counted from the day the book was started, if that is within the window, so a new book is not diluted.
                $startedOn = $userBook->started_at?->setTimezone($this->user->timezone)->toDateString();
                $since = $startedOn !== null ? max($startedOn, $windowStart->toDateString()) : $windowStart->toDateString();

                $pace = $days->sum() / (self::daysBetween($since, $today->toDateString()) + 1);
            }

            $remaining = (int) $userBook->total_pages - $userBook->current_page;
            $daysLeft = $remaining <= 0 ? 0 : ($pace > 0 ? (int) ceil($remaining / $pace) : null);

            if ($daysLeft !== null && $daysLeft > self::MAX_FORECAST_DAYS) {
                $daysLeft = null;
            }

            return [
                'user_book_id' => $userBook->id,
                'title' => $userBook->book->title,
                'current_page' => $userBook->current_page,
                'total_pages' => (int) $userBook->total_pages,
                'pages_per_day' => $pace > 0 ? round($pace, 1) : null,
                'days_left' => $daysLeft,
                'finish_on' => $daysLeft === null ? null : $today->addDays($daysLeft)->toDateString(),
            ];
        })->values()->all();
    }
}
