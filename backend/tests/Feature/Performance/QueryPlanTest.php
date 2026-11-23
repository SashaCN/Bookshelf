<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Query plans at a realistic volume. Data is bulk-inserted inside the test transaction (rolled back afterwards);
 * every SELECT of a hot endpoint is captured and EXPLAINed, and none may scan a big table in full.
 * Set PERF_REPORT=1 to print the timings of each endpoint to stderr.
 */

const PERF_BIG_TABLES = ['quotes', 'follows', 'quote_likes', 'quote_comments', 'reading_logs', 'user_books', 'reader_chapter_reads', 'reader_documents'];

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

/** @return array{me: User, star: string, quote: int} */
function perfSeed(): array
{
    mt_srand(42);
    $now = now()->toDateTimeString();
    $users = 2000;

    $rows = [];
    for ($i = 1; $i <= $users; $i++) {
        $public = $i % 2 === 1;
        $rows[] = [
            'id' => $i,
            'name' => 'Reader '.Str::random(6).' '.$i,
            'email' => "perf{$i}@example.test",
            'password' => 'x',
            'timezone' => 'Europe/Kyiv',
            'username' => $public ? 'reader'.$i : null,
            'is_public' => $public,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    perfInsert('users', $rows);

    $books = [];
    for ($i = 1; $i <= 500; $i++) {
        $books[] = ['id' => $i, 'title' => "Book $i", 'source' => 'manual', 'created_at' => $now, 'updated_at' => $now];
    }
    perfInsert('books', $books);

    // User 1 ("me") and user 3 ("star", public) get the heavy data; the rest is spread around.
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
            'started_at' => $now, 'finished_at' => $status === 'finished' ? $now : null,
            'review_visibility' => 'private', 'created_at' => $now, 'updated_at' => $now,
        ];
    }
    perfInsert('user_books', $userBooks);
    $myBook = (int) DB::table('user_books')->where('user_id', 1)->value('id');
    $firstBook = (int) DB::table('user_books')->min('id');

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

    $quotes = [];
    for ($i = 1; $i <= 30000; $i++) {
        $user = $i <= 1000 ? 1 : ($i <= 4000 ? 3 : mt_rand(1, $users));
        $quotes[] = [
            'user_book_id' => $user === 1 ? $myBook : $firstBook, 'user_id' => $user, 'type' => $i % 5 === 0 ? 'insight' : 'quote',
            'content' => 'Some quote text '.$i, 'is_favorite' => $i % 7 === 0,
            'visibility' => $i % 10 < 3 ? 'public' : 'private', 'created_at' => $now, 'updated_at' => $now,
        ];
    }
    perfInsert('quotes', $quotes);
    $quote = (int) DB::table('quotes')->where('user_id', 3)->where('visibility', 'public')->value('id');

    // Auto-increment ids do not restart after a rolled-back transaction, so the real range is read back.
    $firstQuote = (int) DB::table('quotes')->min('id');
    $lastQuote = (int) DB::table('quotes')->max('id');

    $likes = [];
    while (count($likes) < 20000) {
        $u = mt_rand(1, $users);
        $q = mt_rand($firstQuote, $lastQuote);
        $likes["$u-$q"] = ['user_id' => $u, 'quote_id' => $q, 'created_at' => $now];
    }
    perfInsert('quote_likes', array_values($likes));

    $comments = [];
    for ($i = 1; $i <= 5000; $i++) {
        $comments[] = ['quote_id' => $i <= 400 ? $quote : mt_rand($firstQuote, $lastQuote), 'user_id' => mt_rand(1, $users), 'body' => 'Comment '.$i, 'created_at' => $now, 'updated_at' => $now];
    }
    perfInsert('quote_comments', $comments);

    $logs = [];
    for ($i = 0; $i < 10000; $i++) {
        $day = now('Europe/Kyiv')->subDays($i % 500)->toDateString();
        $logs[] = ['user_book_id' => $myBook, 'user_id' => 1, 'from_page' => 1, 'to_page' => 11, 'logged_on' => $day, 'created_at' => $now];
    }
    perfInsert('reading_logs', $logs);

    // Reader: 5 EPUBs for "me", 1000 for the others, and 20000 chapter reads, a third of them "mine".
    $documents = [];
    for ($i = 1; $i <= 1005; $i++) {
        $documents[] = [
            'user_id' => $i <= 5 ? 1 : mt_rand(2, $users), 'title' => "Epub $i", 'path' => "reader/perf/$i.epub",
            'chapters' => '[]', 'chapters_count' => 400, 'position_chapter' => 3, 'position_fraction' => 0.5,
            'created_at' => $now, 'updated_at' => $now,
        ];
    }
    perfInsert('reader_documents', $documents);
    $firstDocument = (int) DB::table('reader_documents')->min('id');

    $reads = [];
    for ($i = 0; $i < 20000; $i++) {
        $document = $i < 7000 ? $firstDocument + ($i % 5) : $firstDocument + 5 + ($i % 1000);
        $reads["$document-".intdiv($i, 5)] = [
            'reader_document_id' => $document, 'chapter' => intdiv($i, 5), 'read_on' => now('Europe/Kyiv')->subDays($i % 400)->toDateString(), 'created_at' => $now,
        ];
    }
    perfInsert('reader_chapter_reads', array_values($reads));

    return ['me' => User::findOrFail(1), 'star' => 'reader3', 'quote' => $quote];
}

/** @return list<array{string, string, int}> label, path, status */
function perfEndpoints(array $seed): array
{
    return [
        ['feed', '/api/feed', 200],
        ['profile', "/api/users/{$seed['star']}", 200],
        ['profile quotes', "/api/users/{$seed['star']}/quotes", 200],
        ['followers', "/api/users/{$seed['star']}/followers", 200],
        ['search prefix', '/api/users?q=reader12', 200],
        ['search name', '/api/users?q=Reader', 200],
        ['quotes list', '/api/quotes', 200],
        ['quotes filtered', '/api/quotes?favorite=1&type=quote', 200],
        // Rows inserted in the test transaction are invisible to the FULLTEXT index, but the plan must still use it.
        ['quotes search', '/api/quotes?q=quote', 200],
        ['quotes search filtered', '/api/quotes?q=quote+text&favorite=1', 200],
        ['comments', "/api/quotes/{$seed['quote']}/comments", 200],
        ['stats summary', '/api/stats/summary', 200],
        ['stats daily', '/api/stats/daily?days=365', 200],
        ['library', '/api/library', 200],
        ['library status', '/api/library?status=reading', 200],
    ];
}

it('keeps every hot endpoint off full table scans at volume', function () {
    $seed = perfSeed();
    $this->actingAs($seed['me'], 'sanctum');

    $failures = [];
    $report = [];
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query;
    });

    foreach (perfEndpoints($seed) as [$label, $path, $status]) {
        $queries = [];
        $started = hrtime(true);
        $this->getJson($path)->assertStatus($status);
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
