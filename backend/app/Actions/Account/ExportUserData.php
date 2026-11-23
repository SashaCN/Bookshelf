<?php

namespace App\Actions\Account;

use App\Models\Quote;
use App\Models\QuoteComment;
use App\Models\ReaderBookmark;
use App\Models\ReaderChapterRead;
use App\Models\ReaderDocument;
use App\Models\ReadingGoal;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserBook;
use App\Models\UserBookRead;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ExportUserData
{
    public const FORMAT = 'bookshelf-export';

    public const VERSION = 1;

    private const CHUNK_SIZE = 500;

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    /**
     * Everything the reader owns as one JSON document, produced piece by piece (the big lists in chunks,
     * with their relations loaded per chunk), so memory stays flat however large the library is.
     * The EPUB files and other readers' comments and likes are not part of it.
     *
     * @return Generator<int, string>
     */
    public function handle(User $user): Generator
    {
        yield '{"format":'.$this->encode(self::FORMAT).',"version":'.self::VERSION.',"exported_at":'.$this->encode(now()->toIso8601String());
        yield ',"profile":'.$this->encode([
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'is_public' => $user->is_public,
            'timezone' => $user->timezone,
            'created_at' => $this->date($user->created_at),
        ]);

        yield from $this->section('library', $this->library($user));
        yield from $this->section('reading_logs', $this->readingLogs($user));
        yield from $this->section('quotes', $this->quotes($user));
        yield from $this->section('comments', $this->comments($user));
        yield from $this->section('goals', $this->goals($user));
        yield from $this->section('following', $this->follows($user->id, 'follower_id', 'followed_id'));
        yield from $this->section('followers', $this->follows($user->id, 'followed_id', 'follower_id'));
        yield from $this->section('blocks', $this->blocks($user));
        yield from $this->section('reader_documents', $this->readerDocuments($user));

        yield "}\n";
    }

    /**
     * One key of the document with its items as a JSON array, one item per line.
     *
     * @param  iterable<int, mixed>  $items
     * @return Generator<int, string>
     */
    private function section(string $key, iterable $items): Generator
    {
        yield ",\n".$this->encode($key).':[';

        $first = true;
        foreach ($items as $item) {
            yield ($first ? "\n" : ",\n").$this->encode($item);
            $first = false;
        }

        yield $first ? ']' : "\n]";
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    private function library(User $user): LazyCollection
    {
        return UserBook::query()
            ->whereBelongsTo($user)
            ->with('book.authors', 'reads')
            ->lazyById(self::CHUNK_SIZE)
            ->map(fn (UserBook $entry) => [
                'id' => $entry->id,
                'title' => $entry->book->title,
                'authors' => $entry->book->authors->pluck('name')->all(),
                'isbn' => $entry->book->isbn_13,
                'status' => $entry->status->value,
                'total_pages' => $entry->total_pages,
                'current_page' => $entry->current_page,
                'rating' => $entry->rating,
                'review' => $entry->review,
                'review_visibility' => $entry->review_visibility->value,
                'reviewed_at' => $this->date($entry->reviewed_at),
                'started_at' => $this->date($entry->started_at),
                'finished_at' => $this->date($entry->finished_at),
                'added_at' => $this->date($entry->created_at),
                'previous_reads' => $entry->reads->map(fn (UserBookRead $read) => [
                    'started_at' => $this->date($read->started_at),
                    'finished_at' => $this->date($read->finished_at),
                ])->all(),
            ]);
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    private function readingLogs(User $user): LazyCollection
    {
        return ReadingLog::query()
            ->whereBelongsTo($user)
            ->with('userBook.book')
            ->lazyById(self::CHUNK_SIZE)
            ->map(fn (ReadingLog $log) => [
                'library_id' => $log->user_book_id,
                'book' => $log->userBook->book->title,
                'from_page' => $log->from_page,
                'to_page' => $log->to_page,
                'logged_on' => $log->logged_on->toDateString(),
                'created_at' => $this->date($log->created_at),
            ]);
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    private function quotes(User $user): LazyCollection
    {
        return Quote::query()
            ->whereBelongsTo($user)
            ->with('userBook.book', 'tags')
            ->lazyById(self::CHUNK_SIZE)
            ->map(fn (Quote $quote) => [
                'id' => $quote->id,
                'library_id' => $quote->user_book_id,
                'book' => $quote->userBook->book->title,
                'type' => $quote->type->value,
                'content' => $quote->content,
                'note' => $quote->note,
                'page' => $quote->page,
                'is_favorite' => $quote->is_favorite,
                'visibility' => $quote->visibility->value,
                'tags' => $quote->tags->pluck('name')->all(),
                'created_at' => $this->date($quote->created_at),
            ]);
    }

    /**
     * The reader's own comments (on any quote); the id is the quote they were left under.
     *
     * @return LazyCollection<int, mixed>
     */
    private function comments(User $user): LazyCollection
    {
        return QuoteComment::query()
            ->whereBelongsTo($user)
            ->lazyById(self::CHUNK_SIZE)
            ->map(fn (QuoteComment $comment) => [
                'quote_id' => $comment->quote_id,
                'body' => $comment->body,
                'created_at' => $this->date($comment->created_at),
            ]);
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    private function goals(User $user): LazyCollection
    {
        return ReadingGoal::query()
            ->whereBelongsTo($user)
            ->lazyById(self::CHUNK_SIZE)
            ->map(fn (ReadingGoal $goal) => [
                'year' => $goal->year,
                'target_books' => $goal->target_books,
            ]);
    }

    /**
     * The other side of the reader's follows. A private profile is only a null: the export must not reveal who it is.
     *
     * @return LazyCollection<int, mixed>
     */
    private function follows(int $userId, string $ownColumn, string $otherColumn): LazyCollection
    {
        return DB::table('follows')
            ->join('users', 'users.id', '=', "follows.{$otherColumn}")
            ->where("follows.{$ownColumn}", $userId)
            ->orderBy('follows.created_at')
            ->orderBy("follows.{$otherColumn}")
            ->select(['users.username', 'users.is_public', 'follows.created_at'])
            ->cursor()
            ->map(fn (object $row) => [
                'username' => $row->is_public ? $row->username : null,
                'since' => $row->created_at === null ? null : $this->date(Carbon::parse($row->created_at)),
            ]);
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    private function blocks(User $user): LazyCollection
    {
        return DB::table('user_blocks')
            ->join('users', 'users.id', '=', 'user_blocks.blocked_id')
            ->where('user_blocks.blocker_id', $user->id)
            ->orderBy('user_blocks.created_at')
            ->orderBy('user_blocks.blocked_id')
            ->select(['users.username'])
            ->cursor()
            ->map(fn (object $row) => $row->username);
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    private function readerDocuments(User $user): LazyCollection
    {
        return ReaderDocument::query()
            ->whereBelongsTo($user)
            ->with('bookmarks', 'chapterReads', 'userBook.book')
            ->lazyById(100)
            ->map(fn (ReaderDocument $document) => [
                'title' => $document->title,
                'author' => $document->author,
                'library_book' => $document->userBook?->book->title,
                'chapters_count' => $document->chapters_count,
                'position_chapter' => $document->position_chapter,
                'position_fraction' => $document->position_fraction,
                'last_read_at' => $this->date($document->last_read_at),
                'bookmarks' => $document->bookmarks->map(fn (ReaderBookmark $bookmark) => [
                    'chapter' => $bookmark->chapter,
                    'note' => $bookmark->note,
                    'created_at' => $this->date($bookmark->created_at),
                ])->all(),
                'chapter_reads' => $document->chapterReads->map(fn (ReaderChapterRead $read) => [
                    'chapter' => $read->chapter,
                    'read_on' => $this->date($read->read_on),
                ])->all(),
            ]);
    }

    private function date(CarbonInterface|string|null $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : $value;
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAGS);
    }
}
