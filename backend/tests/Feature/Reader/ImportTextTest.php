<?php

use App\Models\BookChapter;
use App\Models\BookText;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;
use App\Services\Epub\EpubImporter;
use App\Services\Epub\EpubLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\Support\EpubBuilder;

function uploadBookText(mixed $test, User $user, UserBook $userBook, ?UploadedFile $file, array $extra = [])
{
    return $test->actingAs($user)
        ->withHeader('Accept', 'application/json')
        ->post("/api/library/{$userBook->id}/text", array_filter(['file' => $file] + $extra, fn ($value) => $value !== null));
}

/**
 * Every file below a directory, by path (and nothing else about it).
 *
 * @return list<string>
 */
function filesBelow(string $directory): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Small limits for the tests that would otherwise need a very big book. The importer and the action take them from the
 * container like everything else.
 */
function useLimits(EpubLimits $limits): void
{
    app()->instance(EpubLimits::class, $limits);
}

function sampleEpub(): EpubBuilder
{
    return EpubBuilder::make()
        ->chapter('Розділ перший', '<p>Це початок <em>книги</em>.</p>')
        ->chapter('Chapter two', '<h1>Heading</h1><p>Second 😀 chapter.</p>');
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);
});

it('requires authentication for every endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['POST', '/api/library/1/text'],
    ['GET', '/api/library/1/text'],
    ['DELETE', '/api/library/1/text'],
    ['GET', '/api/book-texts/1/chapters/0'],
    ['PUT', '/api/book-texts/1/position'],
]);

describe('uploading a book', function () {
    it('keeps the sanitized chapters of the book and answers with its table of contents', function () {
        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload('Моя книга.epub'))
            ->assertCreated()
            ->assertJsonPath('data.user_book_id', $this->userBook->id)
            ->assertJsonPath('data.source_name', 'Моя книга.epub')
            ->assertJsonPath('data.chapters_count', 2)
            ->assertJsonPath('data.characters_count', 17 + 25)
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.position.chapter', 0)
            ->assertJsonPath('data.position.chapter_progress', 0)
            ->assertJsonPath('data.position.updated_at', null)
            ->assertJsonPath('data.chapters.0.position', 0)
            ->assertJsonPath('data.chapters.0.title', 'Розділ перший')
            ->assertJsonPath('data.chapters.0.characters', 17)
            ->assertJsonPath('data.chapters.1.title', 'Chapter two')
            ->assertJsonPath('data.chapters.1.characters', 25)
            ->assertJsonMissingPath('data.chapters.0.html');

        $text = BookText::query()->sole();
        expect($text->user_id)->toBe($this->user->id)
            ->and($text->user_book_id)->toBe($this->userBook->id)
            ->and($text->chapters()->count())->toBe(2)
            ->and($text->chapters()->where('position', 0)->value('content'))->toBe('<p>Це початок <em>книги</em>.</p>')
            ->and($text->chapters()->where('position', 1)->value('content'))->toBe('<h1>Heading</h1><p>Second 😀 chapter.</p>');
    });

    it('does not keep the file itself anywhere', function () {
        $upload = sampleEpub()->toUpload();
        $uploaded = $upload->getRealPath();
        $temporary = fn () => array_values(array_diff(
            array_merge(glob(sys_get_temp_dir().'/php*') ?: [], glob(sys_get_temp_dir().'/epub*') ?: []),
            [$uploaded],
        ));

        $storageBefore = filesBelow(storage_path());
        $temporaryBefore = $temporary();

        uploadBookText($this, $this->user, $this->userBook, $upload)->assertCreated();

        // No new file anywhere in the storage of the app (not under "app", not in the cache, the logs or the framework's
        // own directories), and none left in the temporary directory either.
        expect(filesBelow(storage_path()))->toBe($storageBefore)
            ->and($temporary())->toBe($temporaryBefore)
            // And not in the database: what is stored is the cleaned-up text, never a piece of the archive.
            ->and(BookChapter::query()->pluck('content')->implode(''))->not->toContain("PK\x03\x04")
            ->and(BookText::query()->sole()->source_name)->toBe('book.epub');
    });

    it('stores only what survives the sanitizer, whatever the book carries', function () {
        $hostile = '<script>alert(1)</script><p onclick="steal()">Safe <a href="javascript:alert(2)">text</a><img src="x" onerror="alert(3)"/></p>'
            .'<iframe src="javascript:alert(4)"></iframe><svg onload="alert(5)"><text>x</text></svg><style>@import url(http://evil.example/a.css);</style>';

        uploadBookText($this, $this->user, $this->userBook, EpubBuilder::make()->chapter('Hostile', $hostile)->toUpload())->assertCreated();

        expect(BookChapter::query()->sole()->content)->toBe('<p>Safe text</p>');
    });

    it('puts the library entry in the answers about it', function () {
        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload())->assertCreated();

        $this->actingAs($this->user)->getJson("/api/library/{$this->userBook->id}")
            ->assertOk()
            ->assertJsonPath('data.has_text', true)
            ->assertJsonPath('data.text_progress', 0);
    });

    it('replaces the previous text and clears the places its quotes pointed at', function () {
        $old = BookText::factory()->for($this->userBook)->withSampleChapters()->create();
        $anchored = Quote::factory()->for($this->userBook)->create([
            'book_text_id' => $old->id, 'chapter_position' => 0, 'start_offset' => 4, 'end_offset' => 9,
        ]);
        $other = Quote::factory()->for(UserBook::factory()->for($this->user))->create();

        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload())->assertCreated();

        $text = BookText::query()->sole();
        expect($text->id)->not->toBe($old->id)
            ->and(BookChapter::query()->count())->toBe(2)
            ->and(BookChapter::query()->where('book_text_id', $old->id)->count())->toBe(0);

        $anchored->refresh();
        expect(Quote::query()->count())->toBe(2)
            ->and($anchored->book_text_id)->toBeNull()
            ->and($anchored->chapter_position)->toBeNull()
            ->and($anchored->start_offset)->toBeNull()
            ->and($anchored->end_offset)->toBeNull()
            ->and($anchored->content)->toBe($anchored->content);
        expect($other->refresh()->anchor())->toBeNull();
    });

    it('starts a replacing text at the beginning', function () {
        BookText::factory()->for($this->userBook)->withSampleChapters()->at(2, 0.5, 0.8)->create();

        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload())
            ->assertCreated()
            ->assertJsonPath('data.position.chapter', 0)
            ->assertJsonPath('data.progress', 0);
    });

    it('keeps the previous text and its anchors when the new file is refused', function () {
        $old = BookText::factory()->for($this->userBook)->withSampleChapters()->create();
        $anchored = Quote::factory()->for($this->userBook)->create([
            'book_text_id' => $old->id, 'chapter_position' => 0, 'start_offset' => 4, 'end_offset' => 9,
        ]);

        uploadBookText($this, $this->user, $this->userBook, UploadedFile::fake()->createWithContent('book.epub', 'not a book at all, just text'))
            ->assertUnprocessable();

        expect(BookText::query()->sole()->id)->toBe($old->id)
            ->and(BookChapter::query()->count())->toBe(3)
            ->and($anchored->refresh()->anchor())->toBe(['book_text_id' => $old->id, 'chapter' => 0, 'start' => 4, 'end' => 9]);
    });

    it('keeps the previous text when the new book turns out to be bad half way through', function () {
        $old = BookText::factory()->for($this->userBook)->withSampleChapters()->create();

        $book = EpubBuilder::make()
            ->chapter('Fine', '<p>fine</p>')
            ->chapter('Huge', '<p>'.str_repeat('Слово ', 500_000).'</p>');

        uploadBookText($this, $this->user, $this->userBook, $book->toUpload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.chapter_too_large']);

        expect(BookText::query()->sole()->id)->toBe($old->id)
            ->and(BookChapter::query()->count())->toBe(3);
    });

    it('cleans a file name before it is kept', function () {
        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload("../../etc/pass\x01wd.epub"))
            ->assertCreated()
            ->assertJsonPath('data.source_name', 'passwd.epub');
    });

    it('answers "not found" for somebody else\'s library entry, whatever is sent', function () {
        $theirs = UserBook::factory()->for($this->stranger)->create();

        uploadBookText($this, $this->user, $theirs, sampleEpub()->toUpload())->assertNotFound();
        uploadBookText($this, $this->user, $theirs, null)->assertNotFound();

        expect(BookText::query()->count())->toBe(0);
    });

    it('does not take a lot of memory for a big book', function () {
        $builder = EpubBuilder::make();

        for ($i = 0; $i < 50; $i++) {
            $builder->chapter("Chapter {$i}", str_repeat('<p>'.str_repeat('Це довгий абзац тексту. ', 200).'</p>', 30));
        }

        $upload = $builder->toUpload();
        gc_collect_cycles();
        $before = memory_get_usage();
        $peakBefore = memory_get_peak_usage();

        uploadBookText($this, $this->user, $this->userBook, $upload)->assertCreated()->assertJsonPath('data.chapters_count', 50);

        // 50 chapters of about 144 KB of text each: 7.2 MB, and the request itself takes a part of what is measured.
        expect(memory_get_peak_usage() - max($before, $peakBefore))->toBeLessThan(48 * 1024 * 1024);
    });
});

describe('a file that cannot be used', function () {
    it('is refused with the code of the reason', function (callable $file, string $code) {
        uploadBookText($this, $this->user, $this->userBook, $file())
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', [$code]);

        expect(BookText::query()->count())->toBe(0)
            ->and(BookChapter::query()->count())->toBe(0);
    })->with([
        'a text file' => [fn () => UploadedFile::fake()->createWithContent('book.epub', 'Just some words, not an EPUB.'), 'reader.not_epub'],
        'a ZIP archive that is not an EPUB' => [fn () => EpubBuilder::make()->chapter('A', '<p>x</p>')->withMimetype('application/zip')->toUpload(), 'reader.not_epub'],
        'an EPUB-like archive without a mimetype' => [fn () => EpubBuilder::make()->chapter('A', '<p>x</p>')->withMimetype(null)->toUpload(), 'reader.not_epub'],
        'an archive with a path that leaves it' => [fn () => sampleEpub()->entry('../../evil.sh', 'rm -rf /')->toUpload(), 'reader.unsafe_archive'],
        'an archive with an absolute path' => [fn () => sampleEpub()->entry('/etc/cron.d/evil', 'x')->toUpload(), 'reader.unsafe_archive'],
        'a book with DRM' => [fn () => sampleEpub()->withEncryption(
            '<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:enc="http://www.w3.org/2001/04/xmlenc#"><enc:EncryptedData>'
            .'<enc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes128-cbc"/></enc:EncryptedData></encryption>'
        )->toUpload(), 'reader.drm'],
        'a book without anything to read' => [fn () => EpubBuilder::make()->chapter('Cover', '<p><img src="c.jpg"/></p>')->toUpload(), 'reader.empty'],
        'a book with an empty spine' => [fn () => EpubBuilder::make()->toUpload(), 'reader.empty'],
        'a book without a package' => [fn () => sampleEpub()->withContainer('<container/>')->toUpload(), 'reader.invalid'],
        'a book that declares an entity' => [fn () => sampleEpub()->document('Evil', '<?xml version="1.0"?><!DOCTYPE html [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><html xmlns="http://www.w3.org/1999/xhtml"><body><p>&xxe;</p></body></html>')->toUpload(), 'reader.invalid'],
        'a zip bomb' => [fn () => sampleEpub()->largeEntry('OEBPS/bomb.bin', 70 * 1024 * 1024)->toUpload(), 'reader.too_large'],
        'a chapter that is too big' => [fn () => EpubBuilder::make()->chapter('Huge', '<p>'.str_repeat('Слово ', 500_000).'</p>')->toUpload(), 'reader.chapter_too_large'],
    ]);

    it('is refused when it is bigger than 15 MB', function () {
        uploadBookText($this, $this->user, $this->userBook, UploadedFile::fake()->create('big.epub', 16 * 1024, 'application/epub+zip'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.too_large']);

        uploadBookText($this, $this->user, $this->userBook, new UploadedFile(__FILE__, 'huge.epub', null, UPLOAD_ERR_INI_SIZE, true))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.too_large']);
    });

    it('answers "not found" rather than "too large" for somebody else\'s entry', function () {
        $theirs = UserBook::factory()->for($this->stranger)->create();

        uploadBookText($this, $this->user, $theirs, new UploadedFile(__FILE__, 'huge.epub', null, UPLOAD_ERR_INI_SIZE, true))->assertNotFound();
    });

    it('is refused when there is none', function () {
        uploadBookText($this, $this->user, $this->userBook, null)
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.no_file']);

        $this->actingAs($this->user)->postJson("/api/library/{$this->userBook->id}/text", ['file' => 'a string'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.no_file']);

        // A file that did not arrive whole.
        uploadBookText($this, $this->user, $this->userBook, new UploadedFile(__FILE__, 'half.epub', null, UPLOAD_ERR_PARTIAL, true))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.no_file']);
    });

    it('is refused when it has more than 500 chapters', function () {
        $builder = EpubBuilder::make();

        for ($i = 0; $i < 501; $i++) {
            $builder->chapter(null, "<p>text {$i}</p>");
        }

        uploadBookText($this, $this->user, $this->userBook, $builder->toUpload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.too_many_chapters']);
    });

    it('is refused when it has more than 2000 entries', function () {
        $builder = sampleEpub();

        for ($i = 0; $i < 2000; $i++) {
            $builder->entry("images/{$i}.png", '');
        }

        uploadBookText($this, $this->user, $this->userBook, $builder->toUpload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.too_many_entries']);
    });

    it('does not let an entity read a file into the text', function () {
        $xxe = '<?xml version="1.0"?><!DOCTYPE html SYSTEM "file:///etc/passwd"><html xmlns="http://www.w3.org/1999/xhtml"><body><p>Before &xxe; &passwd; after</p></body></html>';

        uploadBookText($this, $this->user, $this->userBook, EpubBuilder::make()->document('Evil', $xxe)->toUpload())->assertCreated();

        expect(BookChapter::query()->sole()->content)->toBe('<p>Before after</p>');
    });
});

describe('how much text a reader may keep', function () {
    /**
     * A text of the given number of characters for an entry of the reader (a text of one chapter).
     */
    function textOf(UserBook $userBook, int $characters): BookText
    {
        return BookText::factory()->for($userBook)->withChapters(['<p>'.str_repeat('a', $characters).'</p>'])->create();
    }

    function epubOf(int $characters): UploadedFile
    {
        return EpubBuilder::make()->chapter('One', '<p>'.str_repeat('b', $characters).'</p>')->toUpload();
    }

    it('is not more than one text may have', function () {
        useLimits(new EpubLimits(maxTextCharacters: 50));

        uploadBookText($this, $this->user, $this->userBook, EpubBuilder::make()
            ->chapter('A', '<p>'.str_repeat('a', 30).'</p>')
            ->chapter('B', '<p>'.str_repeat('b', 30).'</p>')
            ->toUpload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.text_too_large']);

        expect(BookText::query()->count())->toBe(0)
            ->and(BookChapter::query()->count())->toBe(0);

        uploadBookText($this, $this->user, $this->userBook, epubOf(50))->assertCreated();
    });

    it('is not more characters than the quota, counted over all the texts of the reader', function () {
        useLimits(new EpubLimits(maxReaderCharacters: 100));
        textOf(UserBook::factory()->for($this->user)->create(), 60);

        uploadBookText($this, $this->user, $this->userBook, epubOf(41))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.quota_exceeded']);

        expect(BookText::query()->count())->toBe(1)
            ->and(BookChapter::query()->count())->toBe(1);

        uploadBookText($this, $this->user, $this->userBook, epubOf(40))->assertCreated();
    });

    it('is not more texts than the quota', function () {
        useLimits(new EpubLimits(maxReaderTexts: 3));
        textOf(UserBook::factory()->for($this->user)->create(), 5);
        textOf(UserBook::factory()->for($this->user)->create(), 5);
        textOf(UserBook::factory()->for($this->user)->create(), 5);

        uploadBookText($this, $this->user, $this->userBook, epubOf(5))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.quota_exceeded']);

        expect(BookText::query()->count())->toBe(3)
            ->and(BookText::query()->where('user_book_id', $this->userBook->id)->exists())->toBeFalse();
    });

    it('takes the text a new one replaces out of the count first: replacing is not adding', function () {
        useLimits(new EpubLimits(maxReaderCharacters: 100, maxReaderTexts: 2));
        textOf(UserBook::factory()->for($this->user)->create(), 40);
        $old = textOf($this->userBook, 55);

        // 40 elsewhere + 55 here is 95. Counting the old text as well, 60 more would be far over; replacing 55 by 60 is 100.
        uploadBookText($this, $this->user, $this->userBook, epubOf(60))->assertCreated();

        expect(BookText::query()->where('user_book_id', $this->userBook->id)->sole()->characters_count)->toBe(60)
            ->and(BookText::query()->where('user_book_id', $this->userBook->id)->sole()->id)->not->toBe($old->id)
            ->and(BookText::query()->count())->toBe(2);

        // The same rule when the replacement does not fit: the old text stays.
        uploadBookText($this, $this->user, $this->userBook, epubOf(61))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['reader.quota_exceeded']);

        expect(BookText::query()->where('user_book_id', $this->userBook->id)->sole()->characters_count)->toBe(60);
    });

    it('lets a reader who has the most texts replace one of them', function () {
        useLimits(new EpubLimits(maxReaderTexts: 2));
        textOf(UserBook::factory()->for($this->user)->create(), 5);
        textOf($this->userBook, 5);

        uploadBookText($this, $this->user, $this->userBook, epubOf(7))->assertCreated();

        expect(BookText::query()->count())->toBe(2);
    });

    it('does not count what other readers keep', function () {
        useLimits(new EpubLimits(maxReaderCharacters: 100, maxReaderTexts: 1));
        textOf(UserBook::factory()->for($this->stranger)->create(), 90);
        textOf(UserBook::factory()->for($this->stranger)->create(), 90);

        uploadBookText($this, $this->user, $this->userBook, epubOf(100))->assertCreated();
    });

    it('keeps the old text and its anchors when the quota refuses the new one', function () {
        useLimits(new EpubLimits(maxReaderCharacters: 100));
        textOf(UserBook::factory()->for($this->user)->create(), 60);
        $old = textOf($this->userBook, 30);
        $quote = Quote::factory()->for($this->userBook)->create(['book_text_id' => $old->id, 'chapter_position' => 0, 'start_offset' => 1, 'end_offset' => 4]);

        uploadBookText($this, $this->user, $this->userBook, epubOf(41))->assertUnprocessable();

        expect(BookText::query()->where('user_book_id', $this->userBook->id)->sole()->id)->toBe($old->id)
            ->and($quote->refresh()->anchor())->toBe(['book_text_id' => $old->id, 'chapter' => 0, 'start' => 1, 'end' => 4]);
    });
});

describe('one import at a time on the server', function () {
    beforeEach(function () {
        // Do not wait for the five seconds of production.
        useLimits(new EpubLimits(importLockWait: 0));
    });

    it('answers "busy" and stores nothing while another book is being read', function () {
        $other = Cache::lock('epub-import', 60);
        expect($other->get())->toBeTrue();

        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload())
            ->assertStatus(503)
            ->assertHeader('Retry-After', '5')
            ->assertExactJson(['message' => 'reader.busy']);

        expect(BookText::query()->count())->toBe(0)
            ->and(BookChapter::query()->count())->toBe(0);

        // The one who is reading did not lose the lock to the one that was turned away.
        expect(Cache::lock('epub-import', 60)->get())->toBeFalse();
        $other->release();
    });

    it('does not touch what the entry had when it has to wait', function () {
        $old = BookText::factory()->for($this->userBook)->withSampleChapters()->create();
        $other = Cache::lock('epub-import', 60);
        $other->get();

        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload())->assertStatus(503);

        expect(BookText::query()->sole()->id)->toBe($old->id)
            ->and(BookChapter::query()->count())->toBe(3);
        $other->release();
    });

    it('takes the next book as soon as the lock is free', function () {
        $other = Cache::lock('epub-import', 60);
        $other->get();
        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload())->assertStatus(503);

        $other->release();

        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload())->assertCreated();
        expect(BookText::query()->count())->toBe(1);
    });

    it('lets a normal import through, and lets go of the lock afterwards', function () {
        uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload())->assertCreated();

        $lock = Cache::lock('epub-import', 60);
        expect($lock->get())->toBeTrue();
        $lock->release();
    });

    it('lets go of the lock when the book is refused', function (callable $file) {
        uploadBookText($this, $this->user, $this->userBook, $file())->assertUnprocessable();

        $lock = Cache::lock('epub-import', 60);
        expect($lock->get())->toBeTrue();
        $lock->release();
    })->with([
        'not an epub' => [fn () => UploadedFile::fake()->createWithContent('book.epub', 'just text')],
        'drm' => [fn () => sampleEpub()->withEncryption('<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:enc="http://www.w3.org/2001/04/xmlenc#"><enc:EncryptedData><enc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes128-cbc"/></enc:EncryptedData></encryption>')->toUpload()],
        'a chapter that is too big, found half way through' => [fn () => EpubBuilder::make()->chapter('Fine', '<p>fine</p>')->chapter('Huge', '<p>'.str_repeat('Слово ', 500_000).'</p>')->toUpload()],
        'an empty book' => [fn () => EpubBuilder::make()->toUpload()],
    ]);

    it('lets go of the lock when the import fails with something nobody expected', function () {
        $this->mock(EpubImporter::class, fn ($mock) => $mock->shouldReceive('open')->andThrow(new RuntimeException('boom')));
        $this->withoutExceptionHandling();

        expect(fn () => uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload()))->toThrow(RuntimeException::class, 'boom');

        $lock = Cache::lock('epub-import', 60);
        expect($lock->get())->toBeTrue();
        $lock->release();
    });

    it('does not take the lock for a file that is refused before it is read', function () {
        uploadBookText($this, $this->user, $this->userBook, null)->assertUnprocessable();
        uploadBookText($this, $this->stranger, $this->userBook, sampleEpub()->toUpload())->assertNotFound();

        $lock = Cache::lock('epub-import', 60);
        expect($lock->get())->toBeTrue();
        $lock->release();
    });
});

it('limits how often a reader can upload', function () {
    for ($i = 0; $i < 20; $i++) {
        uploadBookText($this, $this->user, $this->userBook, UploadedFile::fake()->createWithContent('b.epub', 'nope'))->assertUnprocessable();
    }

    uploadBookText($this, $this->user, $this->userBook, sampleEpub()->toUpload())->assertTooManyRequests();
});
