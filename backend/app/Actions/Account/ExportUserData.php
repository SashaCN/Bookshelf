<?php

namespace App\Actions\Account;

use App\Models\Quote;
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
     * Everything the reader owns as one JSON document, produced piece by piece (the big lists in chunks, with
     * their relations loaded per chunk), so memory stays flat however large the library is. It holds nothing that
     * belongs to somebody else: other readers appear only as a handle, and the text of uploaded books is left out
     * (only its metadata is listed).
     *
     * @return Generator<int, string>
     */
    public function handle(User $user): Generator
    {
        yield '{"format":'.$this->encode(self::FORMAT).',"version":'.self::VERSION.',"exported_at":'.$this->encode(now()->toIso8601String());
        yield ',"profile":'.$this->encode([
            'handle' => $user->handle,
            'name' => $user->name,
            'email' => $user->email,
            'bio' => $user->bio,
            'timezone' => $user->timezone,
            'created_at' => $this->date($user->created_at),
        ]);

        yield from $this->section('library', $this->library($user));
        yield from $this->section('reading_logs', $this->readingLogs($user));
        yield from $this->section('quotes', $this->quotes($user));
        yield from $this->section('goals', $this->goals($user));
        yield from $this->section('posts', $this->posts($user));
        yield from $this->section('comments', $this->comments($user));
        yield from $this->section('likes', $this->likes($user));
        yield from $this->section('following', $this->follows($user->id, 'follower_id', 'followed_id'));
        yield from $this->section('followers', $this->follows($user->id, 'followed_id', 'follower_id'));
        yield from $this->section('blocks', $this->blocks($user));
        yield from $this->section('reports_filed', $this->reports($user));
        yield from $this->section('uploaded_books', $this->bookTexts($user));

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
                'tags' => $quote->tags->pluck('name')->all(),
                'anchor' => $quote->anchor() === null ? null : [
                    'chapter' => $quote->chapter_position,
                    'start' => $quote->start_offset,
                    'end' => $quote->end_offset,
                ],
                'created_at' => $this->date($quote->created_at),
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
     * What the reader published: a quote post carries the snapshot of the quote, a review carries the rating.
     *
     * @return LazyCollection<int, mixed>
     */
    private function posts(User $user): LazyCollection
    {
        return DB::table('posts')
            ->join('books', 'books.id', '=', 'posts.book_id')
            ->where('posts.user_id', $user->id)
            ->orderBy('posts.id')
            ->select(['posts.id', 'books.title as book', 'posts.type', 'posts.rating', 'posts.content', 'posts.comment', 'posts.page', 'posts.created_at'])
            ->cursor()
            ->map(fn (object $row) => [
                'id' => $row->id,
                'book' => $row->book,
                'type' => $row->type,
                'rating' => $row->rating,
                'content' => $row->content,
                'comment' => $row->comment,
                'page' => $row->page,
                'created_at' => $this->raw($row->created_at),
            ]);
    }

    /**
     * The reader's own comments, under any post; only the handle of the post's author is named.
     *
     * @return LazyCollection<int, mixed>
     */
    private function comments(User $user): LazyCollection
    {
        return DB::table('comments')
            ->join('posts', 'posts.id', '=', 'comments.post_id')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('comments.user_id', $user->id)
            ->orderBy('comments.id')
            ->select(['comments.post_id', 'users.handle as post_author', 'comments.content', 'comments.created_at'])
            ->cursor()
            ->map(fn (object $row) => [
                'post_id' => $row->post_id,
                'post_author' => $row->post_author,
                'content' => $row->content,
                'created_at' => $this->raw($row->created_at),
            ]);
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    private function likes(User $user): LazyCollection
    {
        return DB::table('likes')
            ->join('posts', 'posts.id', '=', 'likes.post_id')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('likes.user_id', $user->id)
            ->orderBy('likes.created_at')
            ->orderBy('likes.post_id')
            ->select(['likes.post_id', 'users.handle as post_author', 'likes.created_at'])
            ->cursor()
            ->map(fn (object $row) => [
                'post_id' => $row->post_id,
                'post_author' => $row->post_author,
                'liked_at' => $this->raw($row->created_at),
            ]);
    }

    /**
     * The other side of the reader's follows, as a handle only.
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
            ->select(['users.handle', 'follows.created_at'])
            ->cursor()
            ->map(fn (object $row) => [
                'handle' => $row->handle,
                'since' => $this->raw($row->created_at),
            ]);
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    private function blocks(User $user): LazyCollection
    {
        return DB::table('blocks')
            ->join('users', 'users.id', '=', 'blocks.blocked_id')
            ->where('blocks.user_id', $user->id)
            ->orderBy('blocks.created_at')
            ->orderBy('blocks.blocked_id')
            ->select(['users.handle'])
            ->cursor()
            ->map(fn (object $row) => $row->handle);
    }

    /**
     * The reports the reader filed (not the ones filed about them, and not the moderator's status).
     *
     * @return LazyCollection<int, mixed>
     */
    private function reports(User $user): LazyCollection
    {
        return DB::table('reports')
            ->where('reporter_id', $user->id)
            ->orderBy('id')
            ->select(['reportable_type', 'reportable_id', 'reason', 'note', 'created_at'])
            ->cursor()
            ->map(fn (object $row) => [
                'about' => $row->reportable_type,
                'id' => $row->reportable_id,
                'reason' => $row->reason,
                'note' => $row->note,
                'created_at' => $this->raw($row->created_at),
            ]);
    }

    /**
     * What the reader uploaded, without the text itself.
     *
     * @return LazyCollection<int, mixed>
     */
    private function bookTexts(User $user): LazyCollection
    {
        return DB::table('book_texts')
            ->join('user_books', 'user_books.id', '=', 'book_texts.user_book_id')
            ->join('books', 'books.id', '=', 'user_books.book_id')
            ->where('book_texts.user_id', $user->id)
            ->orderBy('book_texts.id')
            ->select([
                'book_texts.user_book_id as library_id', 'books.title as book', 'book_texts.source_name',
                'book_texts.chapters_count', 'book_texts.characters_count',
                'book_texts.chapter_position', 'book_texts.progress', 'book_texts.created_at',
            ])
            ->cursor()
            ->map(fn (object $row) => [
                'library_id' => $row->library_id,
                'book' => $row->book,
                'file_name' => $row->source_name,
                'chapters_count' => $row->chapters_count,
                'characters_count' => $row->characters_count,
                'position_chapter' => $row->chapter_position,
                'progress' => $row->progress,
                'uploaded_at' => $this->raw($row->created_at),
            ]);
    }

    private function date(CarbonInterface|string|null $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : $value;
    }

    /** A timestamp straight from the query builder (a string, or null). */
    private function raw(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAGS);
    }
}
