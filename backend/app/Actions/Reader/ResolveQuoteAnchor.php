<?php

namespace App\Actions\Reader;

use App\Enums\ReaderError;
use App\Models\BookChapter;
use App\Models\UserBook;
use App\Services\Epub\Text;
use Illuminate\Validation\ValidationException;

/**
 * Checks where in the text of a book a quote says it comes from, and turns that into the columns of the quote.
 *
 * The range is in UTF-16 code units of the text of the chapter (see App\Services\Epub\Text), the unit of a
 * JavaScript string index. The text in that range has to be the text of the quote, white space aside: a browser
 * puts a line break between two paragraphs where the text of the chapter has nothing.
 */
class ResolveQuoteAnchor
{
    /** A quote is at most 5000 characters; this leaves room for the white space a browser may add. */
    private const MAX_SPAN = 20_000;

    /**
     * @param  array{chapter: int, start: int, end: int}  $anchor
     * @return array{book_text_id: int, chapter_position: int, start_offset: int, end_offset: int}
     *
     * @throws ValidationException
     */
    public function handle(UserBook $userBook, array $anchor, string $content): array
    {
        $text = $userBook->bookText;

        if ($text === null) {
            throw $this->refuse(ReaderError::NoText);
        }

        $chapter = BookChapter::query()
            ->where('book_text_id', $text->getKey())
            ->where('position', $anchor['chapter'])
            ->first();

        if ($chapter === null
            || $anchor['end'] <= $anchor['start']
            || $anchor['end'] > $chapter->characters
            || $anchor['end'] - $anchor['start'] > self::MAX_SPAN) {
            throw $this->refuse(ReaderError::AnchorOutOfRange);
        }

        $selected = Text::slice(Text::ofHtml($chapter->content), $anchor['start'], $anchor['end'] - $anchor['start']);
        $skeleton = $selected === null ? '' : Text::skeleton($selected);

        if ($skeleton === '' || $skeleton !== Text::skeleton($content)) {
            throw $this->refuse(ReaderError::AnchorMismatch);
        }

        return [
            'book_text_id' => $text->getKey(),
            'chapter_position' => $anchor['chapter'],
            'start_offset' => $anchor['start'],
            'end_offset' => $anchor['end'],
        ];
    }

    private function refuse(ReaderError $error): ValidationException
    {
        return ValidationException::withMessages(['anchor' => [$error->value]]);
    }
}
