<?php

use App\Models\ReaderDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Builds an EPUB in a temp file. Each option can break one part of it.
 *
 * @param  array<string, string>  $chapters  zip path => body of the chapter
 * @param  array<string, mixed>  $options
 */
function makeEpub(array $chapters = [], array $options = []): UploadedFile
{
    $chapters = $chapters ?: [
        'OEBPS/ch1.xhtml' => '<h1>One</h1><p>First chapter.</p>',
        'OEBPS/ch2.xhtml' => '<h2>Two</h2><p>Second chapter.</p>',
    ];

    $path = tempnam(sys_get_temp_dir(), 'epub');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    if (($options['mimetype'] ?? true) !== false) {
        $zip->addFromString('mimetype', $options['mimetype'] ?? 'application/epub+zip');
    }

    if (! ($options['no_container'] ?? false)) {
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?>'
            .'<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles>'
            .'<rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
    }

    $manifest = '';
    $spine = '';
    $i = 0;
    foreach ($chapters as $name => $body) {
        $i++;
        $manifest .= '<item id="c'.$i.'" href="'.basename($name).'" media-type="application/xhtml+xml"/>';
        $spine .= '<itemref idref="c'.$i.'"/>';
        $zip->addFromString($name, '<html xmlns="http://www.w3.org/1999/xhtml"><head><title>T'.$i.'</title></head><body>'.$body.'</body></html>');
    }
    $manifest .= '<item id="img" href="cover.png" media-type="image/png"/>';
    if ($options['empty_spine'] ?? false) {
        $spine = '';
    }

    $title = array_key_exists('title', $options) ? $options['title'] : 'A Test Book';
    $opf = '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0"><metadata xmlns:dc="http://purl.org/dc/elements/1.1/">'
        .($title !== null ? "<dc:title>{$title}</dc:title>" : '')
        .'<dc:creator>Jane Author</dc:creator></metadata>'
        ."<manifest>{$manifest}</manifest><spine>{$spine}</spine></package>";
    $zip->addFromString('OEBPS/content.opf', $options['broken_opf'] ?? false ? '<package><metadata>' : $opf);

    for ($j = 0; $j < ($options['extra_entries'] ?? 0); $j++) {
        $zip->addFromString("extra/{$j}.txt", 'x');
    }

    $zip->close();

    return new UploadedFile($path, $options['name'] ?? 'book.epub', 'application/epub+zip', null, true);
}

/**
 * An EPUB whose package is written by hand, to test how the references of the spine are read.
 *
 * @param  array<string, string>  $files  zip path => content, added as they are
 * @param  list<string>  $hrefs  manifest hrefs, in spine order
 */
function makeEpubWith(array $files, array $hrefs, string $opfPath = 'OEBPS/content.opf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'epub');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->addFromString('META-INF/container.xml', '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles>'
        .'<rootfile full-path="'.$opfPath.'" media-type="application/oebps-package+xml"/></rootfiles></container>');

    $manifest = '';
    $spine = '';
    foreach ($hrefs as $i => $href) {
        $manifest .= '<item id="c'.$i.'" href="'.htmlspecialchars($href).'" media-type="application/xhtml+xml"/>';
        $spine .= '<itemref idref="c'.$i.'"/>';
    }
    $zip->addFromString($opfPath, '<package><metadata><title>Hand made</title></metadata>'
        ."<manifest>{$manifest}</manifest><spine>{$spine}</spine></package>");
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    return new UploadedFile($path, 'hand.epub', 'application/epub+zip', null, true);
}

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->stranger = User::factory()->create();
});

it('requires authentication for every endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/reader'],
    ['POST', '/api/reader'],
    ['GET', '/api/reader/1'],
    ['DELETE', '/api/reader/1'],
    ['PUT', '/api/reader/1/position'],
    ['GET', '/api/reader/1/chapters/0'],
]);

describe('uploading', function () {
    it('imports an EPUB and keeps the file', function () {
        $this->actingAs($this->user)->post('/api/reader', ['file' => makeEpub()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'A Test Book')
            ->assertJsonPath('data.author', 'Jane Author')
            ->assertJsonPath('data.chapters_count', 2)
            ->assertJsonPath('data.position.chapter', 0)
            ->assertJsonPath('data.progress', 0);

        $document = ReaderDocument::query()->sole();
        expect($document->user_id)->toBe($this->user->id)
            ->and($document->chapters)->toBe(['OEBPS/ch1.xhtml', 'OEBPS/ch2.xhtml'])
            ->and($document->path)->toStartWith("reader/{$this->user->id}/")->toEndWith('.epub');
        Storage::disk('local')->assertExists($document->path);
    });

    it('keeps the spine order, not the manifest order', function () {
        $chapters = ['OEBPS/b.xhtml' => '<p>B</p>', 'OEBPS/a.xhtml' => '<p>A</p>', 'OEBPS/c.xhtml' => '<p>C</p>'];

        $this->actingAs($this->user)->post('/api/reader', ['file' => makeEpub($chapters)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.chapters_count', 3);

        expect(ReaderDocument::query()->sole()->chapters)->toBe(['OEBPS/b.xhtml', 'OEBPS/a.xhtml', 'OEBPS/c.xhtml']);
    });

    it('falls back to the file name when the book has no title', function () {
        $this->actingAs($this->user)->post('/api/reader', ['file' => makeEpub([], ['title' => null, 'name' => 'my-novel.epub'])], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'my-novel');
    });

    it('rejects a hostile or broken file with a code and stores nothing', function (array $options, string $code) {
        $this->actingAs($this->user)->post('/api/reader', ['file' => makeEpub([], $options)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', $code);

        expect(ReaderDocument::query()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    })->with([
        'no mimetype entry' => [['mimetype' => false], 'reader.not_epub'],
        'wrong mimetype' => [['mimetype' => 'application/zip'], 'reader.not_epub'],
        'no container.xml' => [['no_container' => true], 'reader.invalid_epub'],
        'broken OPF' => [['broken_opf' => true], 'reader.invalid_epub'],
        'empty spine' => [['empty_spine' => true], 'reader.invalid_epub'],
        'too many entries' => [['extra_entries' => 2001], 'reader.zip_bomb'],
    ]);

    it('rejects a text file renamed to .epub', function () {
        $file = UploadedFile::fake()->createWithContent('book.epub', 'this is not a zip file');

        $this->actingAs($this->user)->post('/api/reader', ['file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'reader.not_epub');
    });

    it('rejects a file that is too large', function () {
        $this->actingAs($this->user)->post('/api/reader', ['file' => UploadedFile::fake()->create('a.epub', 21000)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'reader.too_large');
    });

    it('rejects another extension and a missing file', function () {
        $this->actingAs($this->user)->post('/api/reader', ['file' => makeEpub([], ['name' => 'book.pdf'])], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->actingAs($this->user)->postJson('/api/reader')->assertUnprocessable()->assertJsonValidationErrors('file');
    });
});

describe('reading the library of books', function () {
    it('lists only own books, newest first, with progress', function () {
        $older = ReaderDocument::factory()->for($this->user)->create(['position_chapter' => 1, 'position_fraction' => 0.5]);
        $newer = ReaderDocument::factory()->for($this->user)->create();
        ReaderDocument::factory()->for($this->stranger)->create();

        $response = $this->actingAs($this->user)->getJson('/api/reader')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.position.chapter', 1)
            ->assertJsonPath('data.1.position.fraction', 0.5)
            // (1 + 0.5) / 3 chapters
            ->assertJsonPath('data.1.progress', 50);
    });

    it('shows one book', function () {
        $document = ReaderDocument::factory()->for($this->user)->create(['title' => 'Dune']);

        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Dune')
            ->assertJsonPath('data.chapters_count', 3);
    });

    it('deletes the book and its file', function () {
        $this->actingAs($this->user)->post('/api/reader', ['file' => makeEpub()], ['Accept' => 'application/json'])->assertCreated();
        $document = ReaderDocument::query()->sole();

        $this->actingAs($this->user)->deleteJson("/api/reader/{$document->id}")->assertNoContent();

        expect(ReaderDocument::query()->count())->toBe(0);
        Storage::disk('local')->assertMissing($document->path);
    });
});

describe('position', function () {
    it('saves the position', function () {
        $document = ReaderDocument::factory()->for($this->user)->create();

        $this->actingAs($this->user)->putJson("/api/reader/{$document->id}/position", ['chapter' => 2, 'fraction' => 0.25])
            ->assertOk()
            ->assertJsonPath('data.position.chapter', 2)
            ->assertJsonPath('data.position.fraction', 0.25)
            ->assertJsonPath('data.progress', 75);

        expect($document->refresh()->position_chapter)->toBe(2);
    });

    it('validates the position', function (array $body, string $field) {
        $document = ReaderDocument::factory()->for($this->user)->create();

        $this->actingAs($this->user)->putJson("/api/reader/{$document->id}/position", $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'chapter past the end' => [['chapter' => 3, 'fraction' => 0], 'chapter'],
        'negative chapter' => [['chapter' => -1, 'fraction' => 0], 'chapter'],
        'fraction above 1' => [['chapter' => 0, 'fraction' => 1.5], 'fraction'],
        'missing fraction' => [['chapter' => 0], 'fraction'],
    ]);
});

describe('chapters', function () {
    it('returns the chapter body without active content', function () {
        $chapters = [
            'OEBPS/ch1.xhtml' => '<h1>Evil</h1><p onclick="steal()">Text <b>bold</b></p><script>alert(1)</script>'
                .'<iframe src="http://x"></iframe><a href="javascript:alert(1)">x</a><style>p{}</style>',
            'OEBPS/ch2.xhtml' => '<p>Second</p>',
        ];
        $this->actingAs($this->user)->post('/api/reader', ['file' => makeEpub($chapters)], ['Accept' => 'application/json'])->assertCreated();
        $document = ReaderDocument::query()->sole();

        $response = $this->actingAs($this->user)->getJson("/api/reader/{$document->id}/chapters/0")
            ->assertOk()
            ->assertJsonPath('data.index', 0)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.title', 'Evil');

        $html = $response->json('data.html');
        expect($html)->toContain('Text <b>bold</b>')
            ->not->toContain('<script')->not->toContain('alert(1)')->not->toContain('onclick')
            ->not->toContain('<iframe')->not->toContain('javascript:')->not->toContain('<style')
            ->not->toContain('<body');

        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}/chapters/1")
            ->assertOk()
            ->assertJsonPath('data.title', 'T2')
            ->assertJsonPath('data.html', '<p>Second</p>');
    });

    describe('images', function () {
        $png = "\x89PNG\r\n\x1a\n".str_repeat('p', 20);
        $jpeg = "\xFF\xD8\xFF\xE0".str_repeat('j', 20);

        /** Uploads a one-chapter book (OEBPS/text/ch.xhtml) with the given extra files and returns its html. */
        $chapterHtml = function ($test, string $body, array $files = []) {
            $file = makeEpubWith(['OEBPS/text/ch.xhtml' => '<html><body>'.$body.'</body></html>'] + $files, ['text/ch.xhtml']);
            $test->actingAs($test->user)->post('/api/reader', ['file' => $file], ['Accept' => 'application/json'])->assertCreated();
            $id = ReaderDocument::query()->latest('id')->firstOrFail()->id;

            return $test->actingAs($test->user)->getJson("/api/reader/{$id}/chapters/0")->assertOk()->json('data.html');
        };

        it('embeds png and jpeg as data URIs, resolving ../ and encoding', function () use ($chapterHtml, $png, $jpeg) {
            $html = $chapterHtml(
                $this,
                '<img src="../images/a%20b.png?x=1#f" alt="A" srcset="x.png 2x" style="width:9999px" width="10" height="5"><img src="../images/c.jpeg">',
                ['OEBPS/images/a b.png' => $png, 'OEBPS/images/c.jpeg' => $jpeg],
            );

            expect($html)->toContain('src="data:image/png;base64,'.base64_encode($png).'"')
                ->toContain('data:image/jpeg;base64,'.base64_encode($jpeg))
                ->toContain('width="10"')->toContain('height="5"')
                ->not->toContain('srcset')->not->toContain('style=');
        });

        it('removes a missing image and keeps its alt text', function () use ($chapterHtml) {
            $html = $chapterHtml($this, '<p>x<img src="nope.png" alt="Lost picture">y<img src="nope2.png"></p>');

            expect($html)->toContain('Lost picture')->not->toContain('<img');
        });

        it('removes an image over 2 MB', function () use ($chapterHtml, $png) {
            $html = $chapterHtml($this, '<img src="big.png" alt="Big">', ['OEBPS/text/big.png' => $png.random_bytes(2 * 1024 * 1024)]);

            expect($html)->not->toContain('<img')->not->toContain('data:')->toContain('Big');
        });

        it('removes an image whose bytes do not match its type', function () use ($chapterHtml, $jpeg) {
            $html = $chapterHtml($this, '<img src="fake.png" alt="Fake">', ['OEBPS/text/fake.png' => $jpeg]);

            expect($html)->not->toContain('<img')->not->toContain('data:');
        });

        it('rejects paths that leave the archive and files of other types', function () use ($chapterHtml, $png) {
            $html = $chapterHtml(
                $this,
                '<img src="../../../secret.png"><img src="../../../../mimetype"><img src="/OEBPS/images/a.png"><img src="doc.txt">',
                ['secret.png' => $png, 'OEBPS/images/a.png' => $png, 'OEBPS/text/doc.txt' => $png],
            );

            expect($html)->not->toContain('<img')->not->toContain('data:');
        });

        it('removes images with an external or scheme source', function () use ($chapterHtml) {
            $html = $chapterHtml($this, '<img src="http://example.com/a.png"><img src="//example.com/a.png"><img src="data:image/png;base64,AAAA"><img src="file:///etc/passwd">');

            expect($html)->not->toContain('<img');
        });

        it('stops embedding at 8 MB per chapter', function () use ($chapterHtml, $png) {
            $big = $png.random_bytes(1_900_000);
            $files = [];
            $body = '';
            foreach (range(1, 5) as $i) {
                $files["OEBPS/text/i{$i}.png"] = $big.$i;
                $body .= "<img src=\"i{$i}.png\" alt=\"n{$i}\">";
            }
            $html = $chapterHtml($this, $body, $files);

            expect(substr_count($html, 'data:image/png'))->toBe(4)
                ->and($html)->toContain('n5')->not->toContain('alt="n5"');
        });
    });

    it('answers 404 for a chapter out of range', function (string $n) {
        $this->actingAs($this->user)->post('/api/reader', ['file' => makeEpub()], ['Accept' => 'application/json'])->assertCreated();
        $document = ReaderDocument::query()->sole();

        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}/chapters/{$n}")->assertNotFound();
    })->with(['2', '99', '-1', 'abc']);
});

it('answers 404 for somebody else\'s book on every route', function (string $method, string $uri, array $body) {
    $document = ReaderDocument::factory()->for($this->stranger)->create();

    $this->actingAs($this->user)->json($method, str_replace('{id}', (string) $document->id, $uri), $body)->assertNotFound();

    expect(ReaderDocument::query()->count())->toBe(1);
})->with([
    ['GET', '/api/reader/{id}', []],
    ['DELETE', '/api/reader/{id}', []],
    ['PUT', '/api/reader/{id}/position', ['chapter' => 0, 'fraction' => 0]],
    ['PUT', '/api/reader/{id}/position', ['chapter' => 99, 'fraction' => 9]],
    ['GET', '/api/reader/{id}/chapters/0', []],
]);

describe('references of the spine', function () {
    it('resolves encoded, fragment, absolute and parent references to archive entries once each', function () {
        $files = [
            'OEBPS/Text/one two.xhtml' => '<p>one</p>',
            'OEBPS/two.xhtml' => '<p>two</p>',
            'three.xhtml' => '<p>three</p>',
        ];
        $file = makeEpubWith($files, [
            'Text/one%20two.xhtml#part',
            'Text/../two.xhtml?x=1',
            'two.xhtml',
            '/three.xhtml',
            '../../../three.xhtml',
            '#only-fragment',
            'https://example.com/x.xhtml',
            'missing.xhtml',
        ]);

        $this->actingAs($this->user)->postJson('/api/reader', ['file' => $file])->assertCreated()->assertJsonPath('data.chapters_count', 3);

        expect(ReaderDocument::query()->sole()->chapters)
            ->toBe(['OEBPS/Text/one two.xhtml', 'OEBPS/two.xhtml', 'three.xhtml']);
    });

    it('finds the package when it sits in the archive root', function () {
        $file = makeEpubWith(['a.xhtml' => '<p>a</p>'], ['a.xhtml'], 'content.opf');

        $this->actingAs($this->user)->postJson('/api/reader', ['file' => $file])->assertCreated();

        expect(ReaderDocument::query()->sole()->chapters)->toBe(['a.xhtml']);
    });

    it('rejects a package that declares entities', function () {
        $file = makeEpubWith(['OEBPS/a.xhtml' => '<p>a</p>'], ['a.xhtml']);
        $zip = new ZipArchive;
        $zip->open($file->getRealPath());
        $opf = (string) $zip->getFromName('OEBPS/content.opf');
        $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0"?><!DOCTYPE package [<!ENTITY a "aaaa">]>'.$opf);
        $zip->close();

        $this->actingAs($this->user)->postJson('/api/reader', ['file' => $file])
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'reader.invalid_epub']);
    });
});

describe('chapter sanitising', function () {
    function importedChapter(mixed $test, string $body): string
    {
        $test->actingAs($test->user)->postJson('/api/reader', ['file' => makeEpub(['OEBPS/ch1.xhtml' => $body])])->assertCreated();
        $document = ReaderDocument::query()->latest('id')->firstOrFail();

        return (string) $test->actingAs($test->user)->getJson("/api/reader/{$document->id}/chapters/0")->assertOk()->json('data.html');
    }

    it('removes script vectors in any spelling', function (string $body, array $gone) {
        $html = strtolower(importedChapter($this, $body));

        foreach ($gone as $needle) {
            expect($html)->not->toContain($needle);
        }
        expect($html)->toContain('kept');
    })->with([
        'uppercase handlers' => ['<p ONCLICK="a()" OnMouseOver="b()">kept</p>', ['onclick', 'onmouseover', 'a()']],
        'svg script and handler' => ['<p>kept</p><svg onload="a()"><script>a()</script><animate attributeName="href" values="javascript:a()"/></svg>', ['<svg', 'script', 'onload', 'javascript']],
        'math link' => ['<p>kept</p><math><mtext href="javascript:a()">x</mtext></math>', ['<math', 'javascript']],
        'data url in a link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">kept</a>', ['data:']],
        'data url in a frame source' => ['<p>kept</p><img src="DATA:image/svg+xml;base64,PHN2Zz4=">', ['data:']],
        'obfuscated scheme' => ["<a href=\" \tjava\nscript:a()\">kept</a>", ['script:']],
        'entity-encoded scheme' => ['<a href="&#106;avascript:a()">kept</a>', ['avascript']],
        'srcdoc' => ['<p>kept</p><iframe srcdoc="<script>a()</script>"></iframe><div SRCDOC="x">y</div>', ['srcdoc', 'iframe']],
        'meta refresh and base' => ['<p>kept</p><meta http-equiv="refresh" content="0;url=javascript:a()"><base href="http://evil/">', ['<meta', '<base']],
        'form action' => ['<p>kept</p><form action="javascript:a()"><button formaction="javascript:b()">x</button></form>', ['javascript']],
    ]);

    it('keeps ordinary links and drops images that are not in the archive', function () {
        $html = importedChapter($this, '<p>kept <a href="ch2.xhtml#x">next</a><img src="data:image/png;base64,iVBORw0KGgo="></p>');

        expect($html)->toContain('href="ch2.xhtml#x"')->not->toContain('<img');
    });

    it('reads a chapter in the encoding it declares', function () {
        $cyrillic = '<?xml version="1.0" encoding="windows-1251"?><html><head><title>t</title></head><body><h1>'
            .mb_convert_encoding('Привіт', 'Windows-1251', 'UTF-8').'</h1><p>kept</p></body></html>';
        $file = makeEpubWith(['OEBPS/a.xhtml' => $cyrillic], ['a.xhtml']);
        $this->actingAs($this->user)->postJson('/api/reader', ['file' => $file])->assertCreated();
        $document = ReaderDocument::query()->sole();

        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}/chapters/0")
            ->assertOk()->assertJsonPath('data.title', 'Привіт');
    });

    it('survives invalid UTF-8 and UTF-16 chapters', function () {
        $files = [
            'OEBPS/a.xhtml' => "<p>kept \xC3\x28 bad</p>",
            'OEBPS/b.xhtml' => "\xFF\xFE".mb_convert_encoding('<p>wide</p>', 'UTF-16LE', 'UTF-8'),
        ];
        $this->actingAs($this->user)->postJson('/api/reader', ['file' => makeEpubWith($files, ['a.xhtml', 'b.xhtml'])])->assertCreated();
        $document = ReaderDocument::query()->sole();

        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}/chapters/0")->assertOk();
        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}/chapters/1")->assertOk()->assertJsonPath('data.html', '<p>wide</p>');
    });

    it('answers 404 for a missing archive and for a huge chapter number', function () {
        $document = ReaderDocument::factory()->for($this->user)->create();
        Storage::disk('local')->put($document->path, 'not a zip');

        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}/chapters/0")->assertNotFound();
        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}/chapters/99999999999999999999")->assertNotFound();
    });
});

describe('failures while storing', function () {
    it('removes the file when the row cannot be saved', function () {
        ReaderDocument::creating(fn () => throw new RuntimeException('database down'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->user)->postJson('/api/reader', ['file' => makeEpub()]);
        } catch (RuntimeException) {
        }

        expect(Storage::disk('local')->allFiles())->toBe([]);
        ReaderDocument::flushEventListeners();
    });

    it('keeps the file when the row cannot be deleted', function () {
        $this->actingAs($this->user)->postJson('/api/reader', ['file' => makeEpub()])->assertCreated();
        $document = ReaderDocument::query()->sole();
        ReaderDocument::deleting(fn () => throw new RuntimeException('database down'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->user)->deleteJson("/api/reader/{$document->id}");
        } catch (RuntimeException) {
        }

        expect(Storage::disk('local')->exists($document->path))->toBeTrue();
        ReaderDocument::flushEventListeners();
    });

    it('throttles uploads per reader', function () {
        $this->actingAs($this->user);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/reader', [])->assertUnprocessable();
        }

        $this->postJson('/api/reader', [])->assertTooManyRequests();
        $this->actingAs($this->stranger)->postJson('/api/reader', [])->assertUnprocessable();
    });
});

describe('table of contents', function () {
    /**
     * A three-chapter EPUB with a hand-written navigation file.
     *
     * @param  array<string, string>  $nav  zip path => content of the nav or ncx file
     */
    function makeTocEpub(array $nav, string $navItem, string $spineAttrs = ''): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'epub');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles>'
            .'<rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $manifest = $navItem;
        $spine = '';
        foreach ([1, 2, 3] as $i) {
            $manifest .= '<item id="c'.$i.'" href="Text/ch'.$i.'.xhtml" media-type="application/xhtml+xml"/>';
            $spine .= '<itemref idref="c'.$i.'"/>';
            $zip->addFromString("OEBPS/Text/ch{$i}.xhtml", '<html><body><p>'.$i.'</p></body></html>');
        }
        $zip->addFromString('OEBPS/content.opf', '<package xmlns="http://www.idpf.org/2007/opf"><metadata><title>Toc</title></metadata>'
            ."<manifest>{$manifest}</manifest><spine {$spineAttrs}>{$spine}</spine></package>");
        foreach ($nav as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'toc.epub', 'application/epub+zip', null, true);
    }

    function navDocument(string $list): string
    {
        return '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body>'
            .'<nav epub:type="landmarks"><ol><li><a href="Text/ch3.xhtml">Landmark</a></li></ol></nav>'
            .'<nav epub:type="toc"><ol>'.$list.'</ol></nav></body></html>';
    }

    function importToc(mixed $test, UploadedFile $file): ReaderDocument
    {
        $test->actingAs($test->user)->postJson('/api/reader', ['file' => $file])->assertCreated();

        return ReaderDocument::query()->sole();
    }

    it('reads titles from the EPUB 3 nav document, relative to it', function () {
        $nav = navDocument('<li><a href="Text/ch1.xhtml">Start</a></li><li><a href="Text/ch3.xhtml">  The   End </a></li>');
        $file = makeTocEpub(['OEBPS/nav.xhtml' => $nav], '<item id="nav" href="nav.xhtml" properties="nav" media-type="application/xhtml+xml"/>');

        expect(importToc($this, $file)->chapter_titles)->toBe(['Start', null, 'The End']);
    });

    it('reads titles from the EPUB 2 NCX', function () {
        $ncx = '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/"><navMap>'
            .'<navPoint><navLabel><text>One</text></navLabel><content src="Text/ch1.xhtml"/></navPoint>'
            .'<navPoint><navLabel><text>Two</text></navLabel><content src="Text/ch2.xhtml"/></navPoint></navMap></ncx>';
        $file = makeTocEpub(['OEBPS/toc.ncx' => $ncx], '<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>', 'toc="ncx"');

        expect(importToc($this, $file)->chapter_titles)->toBe(['One', 'Two', null]);
    });

    it('flattens nested entries and maps fragment hrefs, first title wins', function () {
        $nav = navDocument('<li><a href="Text/ch1.xhtml#top">Part</a><ol>'
            .'<li><a href="Text/ch1.xhtml#s1">Section</a></li><li><a href="Text/ch2.xhtml#s2">Nested</a></li></ol></li>'
            .'<li><a href="Text/missing.xhtml">Gone</a></li><li><a href="https://example.com/">Web</a></li>');
        $file = makeTocEpub(['OEBPS/nav.xhtml' => $nav], '<item id="nav" href="nav.xhtml" properties="nav" media-type="application/xhtml+xml"/>');

        expect(importToc($this, $file)->chapter_titles)->toBe(['Part', 'Nested', null]);
    });

    it('does not fail the import when the navigation is broken', function (string $content) {
        $file = makeTocEpub(['OEBPS/nav.xhtml' => $content], '<item id="nav" href="nav.xhtml" properties="nav" media-type="application/xhtml+xml"/>');

        expect(importToc($this, $file)->chapter_titles)->toBe([null, null, null]);
    })->with([
        'not xml' => ['<nav><ol><li>'],
        'entities' => ['<!DOCTYPE x [<!ENTITY a "b">]><html><body><nav epub:type="toc"><ol><li><a href="Text/ch1.xhtml">&a;</a></li></ol></nav></body></html>'],
        'empty' => [''],
    ]);

    it('does not fail the import when the navigation file is missing', function () {
        $file = makeTocEpub([], '<item id="nav" href="nav.xhtml" properties="nav" media-type="application/xhtml+xml"/>');

        expect(importToc($this, $file)->chapter_titles)->toBe([null, null, null]);
    });

    it('shows the toc of one book but not in the list', function () {
        $document = ReaderDocument::factory()->for($this->user)->create(['chapter_titles' => ['A', null, 'C']]);

        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.toc', [['index' => 0, 'title' => 'A'], ['index' => 2, 'title' => 'C']]);

        $this->actingAs($this->user)->getJson('/api/reader')
            ->assertOk()
            ->assertJsonMissingPath('data.0.toc');
    });

    it('shows an empty toc for a book imported before titles existed', function () {
        $document = ReaderDocument::factory()->for($this->user)->create(['chapter_titles' => null]);

        $this->actingAs($this->user)->getJson("/api/reader/{$document->id}")->assertOk()->assertJsonPath('data.toc', []);
    });
});
