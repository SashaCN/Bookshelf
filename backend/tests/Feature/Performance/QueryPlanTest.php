<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Query plans at a realistic volume. Data is bulk-inserted inside the test transaction (rolled back afterwards);
 * every SELECT of a hot endpoint is captured and EXPLAINed, and none may scan a big table in full.
 * Set PERF_REPORT=1 to print the timings of each endpoint to stderr, PERF_PLANS=1 to print every plan.
 */

const PERF_BIG_TABLES = ['posts', 'follows', 'likes', 'comments', 'quotes', 'quote_tag', 'reading_logs', 'user_books', 'user_book_reads', 'blocks'];

/** @param  list<array<string, mixed>>  $rows */
function perfInsert(string $table, array $rows): void
{
    foreach (array_chunk($rows, 2000) as $chunk) {
        DB::table($table)->insert($chunk);
    }
}

/** Every table access of a plan whose access type is a full scan. @return list<string> */
function perfFullScans(mixed $node): array
{
    $found = [];

    if (is_array($node)) {
        if (($node['access_type'] ?? null) === 'ALL' && isset($node['table_name'])) {
            $found[] = $node['table_name'];
        }
        foreach ($node as $child) {
            $found = [...$found, ...perfFullScans($child)];
        }
    }

    return $found;
}

/** Compact "table type key rows" lines of a plan, for diagnosis. @return list<string> */
function perfAccess(mixed $node): array
{
    $lines = [];
    if (is_array($node)) {
        if (isset($node['table_name'], $node['access_type'])) {
            $lines[] = sprintf('%s %s key=%s rows=%s', $node['table_name'], $node['access_type'], $node['key'] ?? '-', $node['rows_examined_per_scan'] ?? '?');
        }
        foreach ($node as $child) {
            $lines = [...$lines, ...perfAccess($child)];
        }
    }

    return $lines;
}

/** @return array{me: User, star: string, post: int, book: int} */
function perfSeed(): array
{
    mt_srand(42);
    $now = now()->toDateTimeString();
    $users = 2000;

    $rows = [];
    for ($i = 1; $i <= $users; $i++) {
        $rows[] = [
            'id' => $i,
            'name' => 'Reader '.Str::random(6).' '.$i,
            'email' => "perf{$i}@example.test",
            'handle' => 'reader'.$i,
            'password' => 'x',
            'timezone' => 'Europe/Kyiv',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    perfInsert('users', $rows);

    $books = [];
    for ($i = 1; $i <= 500; $i++) {
        $books[] = ['id' => $i, 'title' => "Book $i", 'source' => 'openlibrary', 'created_at' => $now, 'updated_at' => $now];
    }
    perfInsert('books', $books);

    // User 1 ("me") gets the heavy data; user 3 is the "star" everybody follows.
    $userBooks = [];
    $seen = [];
    for ($i = 1; $i <= 5000; $i++) {
        $user = $i <= 300 ? 1 : mt_rand(1, $users);
        $book = mt_rand(1, 500);
        if (isset($seen["$user-$book"])) {
            continue;
        }
        $seen["$user-$book"] = true;
        $status = ['want', 'reading', 'finished'][$i % 3];
        $userBooks[] = [
            'user_id' => $user, 'book_id' => $book, 'status' => $status, 'current_page' => 10, 'total_pages' => 300,
            'started_at' => $now, 'finished_at' => $status === 'finished' ? $now : null, 'created_at' => $now, 'updated_at' => $now,
        ];
    }
    perfInsert('user_books', $userBooks);
    $myBook = (int) DB::table('user_books')->where('user_id', 1)->value('id');
    $firstBook = (int) DB::table('user_books')->min('id');

    $reads = [];
    foreach (DB::table('user_books')->where('status', 'finished')->limit(1500)->pluck('id') as $id) {
        $reads[] = ['user_book_id' => $id, 'started_at' => $now, 'finished_at' => $now, 'created_at' => $now, 'updated_at' => $now];
    }
    perfInsert('user_book_reads', $reads);

    $follows = [];
    // "me" follows 300 readers; "star" (user 3) has 400 followers.
    for ($i = 1; $i <= 300; $i++) {
        $follows['1-'.($i * 2 + 1)] = ['follower_id' => 1, 'followed_id' => $i * 2 + 1, 'created_at' => $now];
    }
    for ($i = 1; $i <= 400; $i++) {
        $follows["$i-3"] = ['follower_id' => $i, 'followed_id' => 3, 'created_at' => $now];
    }
    while (count($follows) < 20000) {
        $a = mt_rand(1, $users);
        $b = mt_rand(1, $users);
        if ($a !== $b) {
            $follows["$a-$b"] = ['follower_id' => $a, 'followed_id' => $b, 'created_at' => $now];
        }
    }
    unset($follows['3-3']);
    perfInsert('follows', array_values($follows));

    $blocks = [];
    for ($i = 0; $i < 30; $i++) {
        $blocks['1-'.(1500 + $i)] = ['user_id' => 1, 'blocked_id' => 1500 + $i, 'created_at' => $now];
        $blocks[(1600 + $i).'-1'] = ['user_id' => 1600 + $i, 'blocked_id' => 1, 'created_at' => $now];
    }
    perfInsert('blocks', array_values($blocks));

    $quotes = [];
    for ($i = 1; $i <= 30000; $i++) {
        $user = $i <= 1000 ? 1 : mt_rand(1, $users);
        $quotes[] = [
            'user_book_id' => $user === 1 ? $myBook : $firstBook, 'user_id' => $user, 'type' => $i % 5 === 0 ? 'insight' : 'quote',
            'content' => 'Some quote text number '.$i, 'note' => $i % 3 === 0 ? 'A note about it' : null, 'is_favorite' => $i % 7 === 0,
            'created_at' => $now, 'updated_at' => $now,
        ];
    }
    perfInsert('quotes', $quotes);
    $myQuotes = DB::table('quotes')->where('user_id', 1)->limit(500)->pluck('id');

    $tags = [];
    foreach (range(1, 20) as $n) {
        $tags[] = ['id' => $n, 'user_id' => 1, 'name' => "tag$n", 'created_at' => $now, 'updated_at' => $now];
    }
    perfInsert('tags', $tags);
    $quoteTags = [];
    foreach ($myQuotes as $index => $quoteId) {
        $quoteTags[] = ['quote_id' => $quoteId, 'tag_id' => ($index % 20) + 1];
    }
    perfInsert('quote_tag', $quoteTags);

    // Posts: mostly quote posts; one review per (reader, book) at most, as the unique key demands.
    $posts = [];
    $reviewed = [];
    for ($i = 1; $i <= 30000; $i++) {
        $user = $i <= 1000 ? 3 : mt_rand(1, $users);
        $book = mt_rand(1, 500);
        $review = $i % 6 === 0 && ! isset($reviewed["$user-$book"]);
        if ($review) {
            $reviewed["$user-$book"] = true;
        }
        $posts[] = [
            'user_id' => $user, 'book_id' => $book, 'type' => $review ? 'review' : 'quote',
            'content' => 'Post text '.$i, 'rating' => $review ? mt_rand(1, 5) : null,
            'created_at' => $now, 'updated_at' => $now,
        ];
    }
    perfInsert('posts', $posts);
    $post = (int) DB::table('posts')->where('user_id', 3)->value('id');
    $firstPost = (int) DB::table('posts')->min('id');
    $lastPost = (int) DB::table('posts')->max('id');
    // The reviews of a book of my own library (the reviews of any other book are not for me to list), a few hundred of them.
    $reviewedBook = (int) DB::table('user_books')->where('user_id', 1)->value('book_id');
    $reviews = [];
    for ($u = 10; $u < 410; $u++) {
        $reviews[] = ['user_id' => $u, 'book_id' => $reviewedBook, 'type' => 'review', 'content' => 'A review', 'rating' => mt_rand(1, 5), 'created_at' => $now, 'updated_at' => $now];
    }
    DB::table('posts')->insertOrIgnore($reviews);

    $likes = [];
    while (count($likes) < 20000) {
        $u = mt_rand(1, $users);
        $p = mt_rand($firstPost, $lastPost);
        $likes["$u-$p"] = ['user_id' => $u, 'post_id' => $p, 'created_at' => $now];
    }
    perfInsert('likes', array_values($likes));

    $comments = [];
    for ($i = 1; $i <= 5000; $i++) {
        $comments[] = ['post_id' => $i <= 400 ? $post : mt_rand($firstPost, $lastPost), 'user_id' => mt_rand(1, $users), 'content' => 'Comment '.$i, 'created_at' => $now, 'updated_at' => $now];
    }
    perfInsert('comments', $comments);

    $logs = [];
    for ($i = 0; $i < 10000; $i++) {
        $day = now('Europe/Kyiv')->subDays($i % 500)->toDateString();
        $logs[] = ['user_book_id' => $myBook, 'user_id' => 1, 'from_page' => 1, 'to_page' => 11, 'logged_on' => $day, 'created_at' => $now];
    }
    perfInsert('reading_logs', $logs);

    return ['me' => User::findOrFail(1), 'star' => 'reader3', 'post' => $post, 'book' => $reviewedBook];
}

/** @return list<array{string, string, int}> label, path, status */
function perfEndpoints(array $seed): array
{
    return [
        ['feed following', '/api/feed', 200],
        ['feed all', '/api/feed?scope=all', 200],
        ['profile', "/api/profiles/{$seed['star']}", 200],
        ['profile posts', "/api/profiles/{$seed['star']}/posts", 200],
        ['search prefix', '/api/profiles?q=reader12', 200],
        ['search name', '/api/profiles?q=Reader', 200],
        ['post', "/api/posts/{$seed['post']}", 200],
        ['comments', "/api/posts/{$seed['post']}/comments", 200],
        ['book reviews', "/api/books/{$seed['book']}/reviews", 200],
        ['blocks', '/api/blocks', 200],
        ['quotes list', '/api/quotes', 200],
        ['quotes filtered', '/api/quotes?favorite=1&type=quote', 200],
        ['quotes search', '/api/quotes?q=quote+text', 200],
        ['quotes tag', '/api/quotes?tag=tag3', 200],
        ['quote of the day', '/api/quotes/daily', 200],
        ['stats summary', '/api/stats/summary', 200],
        ['stats daily', '/api/stats/daily?days=365', 200],
        ['stats year', '/api/stats/year/'.now()->year, 200],
        ['library', '/api/library', 200],
        ['library status', '/api/library?status=reading', 200],
    ];
}

it('keeps every hot endpoint off full table scans at volume', function () {
    $seed = perfSeed();
    $this->actAs($seed['me']);

    $failures = [];
    $report = [];
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query;
    });

    foreach (perfEndpoints($seed) as [$label, $path, $status]) {
        $queries = [];
        $started = hrtime(true);
        expect($this->getJson($path)->getStatusCode())->toBe($status, "$label ($path)");
        $ms = (hrtime(true) - $started) / 1e6;

        $report[] = sprintf('%-16s %7.1f ms  %2d queries', $label, $ms, count($queries));

        expect(count($queries))->toBeLessThanOrEqual(15, "$label ran ".count($queries).' queries');

        foreach ($queries as $query) {
            if (! str_starts_with(ltrim(strtolower($query->sql)), 'select')) {
                continue;
            }
            $plan = json_decode(DB::selectOne('EXPLAIN FORMAT=JSON '.$query->sql, $query->bindings)->EXPLAIN, true);
            if (getenv('PERF_PLANS')) {
                fwrite(STDERR, "\n[$label] ".substr($query->sql, 0, 160)."\n".implode(' | ', perfAccess($plan)));
            }
            foreach (perfFullScans($plan) as $table) {
                if (in_array($table, PERF_BIG_TABLES, true)) {
                    $failures[] = "$label: full scan of $table in: {$query->sql}";
                }
            }
        }
    }

    if (getenv('PERF_REPORT')) {
        fwrite(STDERR, "\n".implode("\n", $report)."\n");
    }

    expect($failures)->toBe([]);
})->group('perf');
