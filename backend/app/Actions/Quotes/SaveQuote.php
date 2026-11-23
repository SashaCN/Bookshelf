<?php

namespace App\Actions\Quotes;

use App\Models\Quote;
use App\Models\Tag;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveQuote
{
    /**
     * @param  array{type?: string, content: string, note?: string|null, page?: int|null, is_favorite?: bool, tags?: list<string>}  $data
     *
     * @throws ValidationException
     */
    public function create(UserBook $userBook, array $data): Quote
    {
        $this->assertPageFitsBook($userBook, $data['page'] ?? null);

        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        $quote = DB::transaction(function () use ($userBook, $data, $tags): Quote {
            $quote = $userBook->quotes()->create($data + ['user_id' => $userBook->user_id]);
            $this->syncTags($quote, $tags);

            return $quote;
        });

        return $quote->refresh()->load('userBook.book.authors', 'tags');
    }

    /**
     * @param  array{type?: string, content?: string, note?: string|null, page?: int|null, is_favorite?: bool, tags?: list<string>}  $data
     *
     * @throws ValidationException
     */
    public function update(Quote $quote, array $data): Quote
    {
        if (array_key_exists('page', $data)) {
            $this->assertPageFitsBook($quote->userBook, $data['page']);
        }

        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        DB::transaction(function () use ($quote, $data, $tags): void {
            $quote->update($data);

            if ($tags !== null) {
                $this->syncTags($quote, $tags);
            }
        });

        return $quote->load('userBook.book.authors', 'tags');
    }

    /**
     * Deletes the quote, and the reader's tags nothing else uses any more.
     */
    public function delete(Quote $quote): void
    {
        DB::transaction(function () use ($quote): void {
            $quote->delete();
            $this->deleteOrphanTags($quote->user_id);
        });
    }

    /**
     * Makes the quote carry exactly these (normalized) tags of its owner, creating the missing ones and dropping
     * the owner's tags that no quote uses afterwards. Runs inside the caller's transaction.
     *
     * @param  list<string>  $names
     */
    private function syncTags(Quote $quote, array $names): void
    {
        $ownerId = $quote->user_id;

        if ($names !== []) {
            Tag::query()->insertOrIgnore(array_map(
                fn (string $name) => ['user_id' => $ownerId, 'name' => $name, 'created_at' => now(), 'updated_at' => now()],
                $names,
            ));
        }

        $ids = Tag::query()->where('user_id', $ownerId)->whereIn('name', $names)->pluck('id')->all();
        $quote->tags()->sync($ids);
        $quote->unsetRelation('tags');

        $this->deleteOrphanTags($ownerId);
    }

    private function deleteOrphanTags(int $userId): void
    {
        Tag::query()->where('user_id', $userId)->whereDoesntHave('quotes')->delete();
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
