<?php

use App\Services\OpenLibrary\Isbn;

it('normalizes valid ISBNs to ISBN-13', function (string $input, string $expected) {
    expect(Isbn::normalize($input))->toBe($expected);
})->with([
    'isbn-13' => ['9780306406157', '9780306406157'],
    'isbn-13 with hyphens' => ['978-0-306-40615-7', '9780306406157'],
    'isbn-13 with spaces and padding' => [' 978 0306406157 ', '9780306406157'],
    'isbn-10' => ['0306406152', '9780306406157'],
    'isbn-10 with hyphens' => ['0-306-40615-2', '9780306406157'],
    'isbn-10 with X check digit' => ['080442957X', '9780804429573'],
    'lowercase x' => ['080442957x', '9780804429573'],
    '979 prefix' => ['9791034312122', '9791034312122'],
]);

it('rejects everything else', function (string $input) {
    expect(Isbn::normalize($input))->toBeNull();
})->with([
    'bad isbn-13 checksum' => ['9780306406158'],
    'bad isbn-10 checksum' => ['0306406153'],
    'wrong prefix' => ['9770306406159'],
    'too short' => ['978030640615'],
    'too long' => ['97803064061570'],
    'text' => ['atomic habits'],
    'X in the middle' => ['03064X6152'],
    'empty' => [''],
]);
