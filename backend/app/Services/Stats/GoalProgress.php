<?php

namespace App\Services\Stats;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * How a reader stands against their yearly goal "N books a year".
 */
final class GoalProgress
{
    /**
     * @return array{year: int, target_books: int|null, finished_books: int, expected_books: float|null, remaining_books: int|null, on_track: bool|null}
     */
    public function handle(User $user, int $year): array
    {
        $statistics = new ReadingStatistics($user);
        $today = $statistics->today();

        $firstDay = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $user->timezone);
        $finished = $statistics->booksFinished($firstDay, $firstDay->endOfYear());
        $target = $user->readingGoals()->where('year', $year)->value('target_books');

        if ($target === null) {
            return [
                'year' => $year,
                'target_books' => null,
                'finished_books' => $finished,
                'expected_books' => null,
                'remaining_books' => null,
                'on_track' => null,
            ];
        }

        // This year the goal is spread evenly over the days; a past year is simply done or not; a future one has not begun.
        [$expected, $due] = match (true) {
            $year === $today->year => [$target * $today->dayOfYear / $today->daysInYear, intdiv($target * $today->dayOfYear, $today->daysInYear)],
            $year < $today->year => [(float) $target, $target],
            default => [0.0, 0],
        };

        return [
            'year' => $year,
            'target_books' => $target,
            'finished_books' => $finished,
            'expected_books' => round($expected, 1),
            'remaining_books' => max(0, $target - $finished),
            // Books are whole: being 0.02 of a book short on the second day of the year is not being behind.
            'on_track' => $year > $today->year ? null : $finished >= $due,
        ];
    }
}
