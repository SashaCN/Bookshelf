<?php

namespace App\Actions\Quotes;

use App\Models\Quote;
use App\Models\UserBook;
use Illuminate\Validation\ValidationException;

class SaveQuote
{
    /**
     * @param  array{type?: string, content: string, note?: string|null, page?: int|null, is_favorite?: bool}  $data
     *
     * @throws ValidationException
     */
    public function create(UserBook $userBook, array $data): Quote
    {
        $this->assertPageFitsBook($userBook, $data['page'] ?? null);

        $quote = $userBook->quotes()->create($data + ['user_id' => $userBook->user_id]);

        return $quote->refresh()->load('userBook.book.authors');
    }

    /**
     * @param  array{type?: string, content?: string, note?: string|null, page?: int|null, is_favorite?: bool}  $data
     *
     * @throws ValidationException
     */
    public function update(Quote $quote, array $data): Quote
    {
        if (array_key_exists('page', $data)) {
            $this->assertPageFitsBook($quote->userBook, $data['page']);
        }

        $quote->update($data);

        return $quote->load('userBook.book.authors');
    }

    /**
     * The reader's own page count is the measure: a quote cannot come from a page the book does not have.
     *
     * @throws ValidationException
     */
    private function assertPageFitsBook(UserBook $userBook, ?int $page): void
    {
        if ($page !== null && $userBook->total_pages !== null && $page > $userBook->total_pages) {
            throw ValidationException::withMessages(['page' => ['quotes.page_above_total']]);
        }
    }
}
