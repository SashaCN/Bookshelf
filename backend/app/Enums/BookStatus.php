<?php

namespace App\Enums;

enum BookStatus: string
{
    case Want = 'want';
    case Reading = 'reading';
    case Finished = 'finished';
    case Abandoned = 'abandoned';

    /**
     * Statuses a book may move to from this one (see docs/mvp-plan.md, "Статуси").
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Want => [self::Reading, self::Finished],
            self::Reading => [self::Finished, self::Abandoned],
            self::Abandoned => [self::Reading],
            self::Finished => [self::Reading],
        };
    }

    public function canMoveTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
