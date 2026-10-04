<?php

use App\Enums\BookStatus;

it('allows exactly the transitions of the plan', function (BookStatus $from, array $allowed) {
    $values = array_map(fn (BookStatus $status) => $status->value, $from->allowedTransitions());

    expect($values)->toEqualCanonicalizing($allowed);
})->with([
    'want' => [BookStatus::Want, ['reading', 'finished']],
    'reading' => [BookStatus::Reading, ['finished', 'abandoned']],
    'abandoned' => [BookStatus::Abandoned, ['reading']],
    'finished' => [BookStatus::Finished, ['reading']],
]);

it('never allows a status to move to itself', function (BookStatus $status) {
    expect($status->canMoveTo($status))->toBeFalse();
})->with(BookStatus::cases());
