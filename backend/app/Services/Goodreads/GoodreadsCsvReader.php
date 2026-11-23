<?php

namespace App\Services\Goodreads;

use Illuminate\Validation\ValidationException;

/**
 * Reads a Goodreads library export: comma separated, UTF-8 (a BOM is tolerated), quoted fields may span lines.
 * Columns are found by header name, so their order and any extra columns do not matter.
 */
final class GoodreadsCsvReader
{
    public const MAX_ROWS = 2000;

    /** Without these the file is not a Goodreads export. */
    private const REQUIRED_HEADERS = ['Title', 'Author', 'Exclusive Shelf'];

    /**
     * @return list<array{row: int, fields: array<string, string>}> `row` counts like a spreadsheet: the header is row 1
     *
     * @throws ValidationException
     */
    public function read(string $contents): array
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            throw $this->invalid();
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        try {
            $headers = fgetcsv($stream, escape: '');

            if ($headers === false) {
                throw $this->invalid();
            }

            $headers = array_map(fn (?string $header): string => trim((string) $header), $headers);

            if (array_diff(self::REQUIRED_HEADERS, $headers) !== []) {
                throw $this->invalid();
            }

            $rows = [];
            $number = 1;

            while (($values = fgetcsv($stream, escape: '')) !== false) {
                $number++;

                // A blank line is not a record.
                if ($values === [null]) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw $this->invalid();
                }

                $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
                $rows[] = ['row' => $number, 'fields' => array_combine($headers, array_map('strval', $values))];
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['file' => ['library.import_invalid']]);
    }
}
