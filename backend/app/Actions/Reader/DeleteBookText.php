<?php

namespace App\Actions\Reader;

use App\Models\BookText;
use App\Models\Quote;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

/**
 * Removes the text of a library entry. The quotes that were selected from it stay, without their place in the text.
 */
class DeleteBookText
{
    /**
     * @return bool whether there was a text to remove
     */
    public function handle(UserBook $userBook): bool
    {
        $text = BookText::query()->where('user_book_id', $userBook->getKey())->first();

        if ($text === null) {
            return false;
        }

        DB::transaction(function () use ($text): void {
            // The database clears `book_text_id` by itself when the text goes; the rest of the anchor is cleared here.
            Quote::query()->where('book_text_id', $text->getKey())->update([
                'book_text_id' => null,
                'chapter_position' => null,
                'start_offset' => null,
                'end_offset' => null,
            ]);

            $text->delete();
        });

        $userBook->unsetRelation('bookText');

        return true;
    }
}
