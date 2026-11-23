<?php

use App\Models\ReaderDocument;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * A valid EPUB as zip path => content: nav, NCX, nested directories, images and a windows-1251 chapter.
 *
 * @return array<string, string>
 */
function fuzzBaseEntries(): array
{
    $png = "\x89PNG\r\n\x1a\n".str_repeat('p', 40);
    $page = fn (string $body) => '<?xml version="1.0" encoding="utf-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><title>T</title></head><body>'.$body.'</body></html>';

    return [
        'mimetype' => 'application/epub+zip',
        'META-INF/container.xml' => '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles>'
            .'<rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>',
        'OEBPS/content.opf' => '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0"><metadata xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:title>Fuzz</dc:title><dc:creator>Author</dc:creator></metadata><manifest>'
            .'<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>'
            .'<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>'
            .'<item id="c1" href="text/ch1.xhtml" media-type="application/xhtml+xml"/>'
            .'<item id="c2" href="text/ch2.xhtml" media-type="application/xhtml+xml"/>'
            .'<item id="c3" href="text/sub/ch3.xhtml" media-type="application/xhtml+xml"/>'
            .'<item id="img" href="images/cover.png" media-type="image/png"/></manifest>'
            .'<spine toc="ncx"><itemref idref="c1"/><itemref idref="c2"/><itemref idref="c3"/></spine></package>',
        'OEBPS/nav.xhtml' => '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol>'
            .'<li><a href="text/ch1.xhtml">One</a></li><li><a href="text/ch2.xhtml#x">Two</a></li></ol></nav></body></html>',
        'OEBPS/toc.ncx' => '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/"><navMap><navPoint id="n1"><navLabel><text>One</text></navLabel>'
            .'<content src="text/ch1.xhtml"/></navPoint></navMap></ncx>',
        'OEBPS/text/ch1.xhtml' => $page('<h1>One</h1><p onclick="x()">Hi <img src="../images/cover.png" alt="c"/></p><script>alert(1)</script>'),
        'OEBPS/text/ch2.xhtml' => '<?xml version="1.0" encoding="windows-1251"?>'.mb_convert_encoding($page('<h2>Глава</h2><p>Привіт</p>'), 'Windows-1251', 'UTF-8'),
        'OEBPS/text/sub/ch3.xhtml' => $page('<p><a href="javascript:alert(1)">x</a></p>'),
        'OEBPS/images/cover.png' => $png,
    ];
}

/**
 * @param  array<string, string>  $entries
 * @param  array<string, mixed>  $options
 */
function fuzzZip(array $entries, array $options = []): string
{
    $path = tempnam(sys_get_temp_dir(), 'fuzz');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    foreach ($entries as $name => $content) {
        $zip->addFromString((string) $name, $content);
        if (($name === 'mimetype' && ! ($options['mimetype_compressed'] ?? false)) || ($options['store_all'] ?? false)) {
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
        }
    }
    $zip->close();
    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

/**
 * @param  array<string, string>  $entries
 * @return array<string, string>
 */
function fuzzReplace(array $entries, string $name, string $content): array
{
    $entries[$name] = $content;

    return $entries;
}

/**
 * Applies one mutation to the entries (or, via $options / $bytes, to the zip itself).
 *
 * @param  array<string, string>  $entries
 * @param  array<string, mixed>  $options
 */
function fuzzMutate(int $kind, array &$entries, array &$options): void
{
    $xmlFiles = ['META-INF/container.xml', 'OEBPS/content.opf', 'OEBPS/nav.xhtml', 'OEBPS/toc.ncx', 'OEBPS/text/ch1.xhtml', 'OEBPS/text/sub/ch3.xhtml'];
    $names = array_keys($entries);
    $file = $xmlFiles[mt_rand(0, count($xmlFiles) - 1)];
    $opf = $entries['OEBPS/content.opf'] ?? '';

    $garbage = [
        '', ' ', 'garbage', "\xEF\xBB\xBF", "\xEF\xBB\xBF".($entries[$file] ?? ''), "\xFF\xFE<\0a\0>\0", "<a>\xC3\x28</a>", "\xFF\xFF\xFF\xFF",
        str_repeat('<a>', 10000), str_repeat('<div>', 10000).'x', str_repeat('<a>', 10000).str_repeat('</a>', 10000),
        '<a b="'.str_repeat('x', 1_000_000).'"/>', '<a '.implode(' ', array_map(fn ($i) => "a$i=\"1\"", range(1, 5000))).'/>',
        '<!DOCTYPE a [<!ENTITY x "y">]><a>&x;</a>', '<!DOCTYPE a SYSTEM "http://127.0.0.1/x.dtd"><a/>',
        '<!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><a>&x;</a>',
        '<?xml version="1.0" encoding="nope"?><a/>', '<?xml version="1.0" encoding="utf-16"?><a/>', "\0\0\0", random_bytes(200),
    ];
    $hrefs = ['a b.xhtml', 'text/ch%311.xhtml', 'text/%E0%A4%A.xhtml', 'тест.xhtml', '../../../etc/passwd', '../..', '/OEBPS/text/ch1.xhtml',
        '', '#', '?', 'http://x/y', 'C:\\x', str_repeat('a/', 5000).'x.xhtml', str_repeat('x', 100000), "a\0b", './', '.', '..', '//', 'text/../text/ch1.xhtml'];

    switch ($kind) {
        case 0: // drop an entry
            unset($entries[$names[mt_rand(0, count($names) - 1)]]);
            break;
        case 1: // garbage in an xml file
            $entries[$file] = $garbage[mt_rand(0, count($garbage) - 1)];
            break;
        case 2: // weird href in the manifest
            $entries['OEBPS/content.opf'] = str_replace('text/ch1.xhtml', htmlspecialchars($hrefs[mt_rand(0, count($hrefs) - 1)]), $opf);
            break;
        case 3: // weird href in nav / ncx
            $target = mt_rand(0, 1) ? 'OEBPS/nav.xhtml' : 'OEBPS/toc.ncx';
            $entries[$target] = str_replace('text/ch1.xhtml', htmlspecialchars($hrefs[mt_rand(0, count($hrefs) - 1)]), $entries[$target] ?? '');
            break;
        case 4: // uppercase extension
            $entries['OEBPS/text/CH1.XHTML'] = $entries['OEBPS/text/ch1.xhtml'] ?? '';
            $entries['OEBPS/content.opf'] = str_replace('text/ch1.xhtml', 'text/CH1.XHTML', $opf);
            break;
        case 5: // missing media type
            $entries['OEBPS/content.opf'] = preg_replace('/ media-type="application\/xhtml\+xml"/', '', $opf, mt_rand(1, 3));
            break;
        case 6: // spine pointing to an image or the opf itself
            $entries['OEBPS/content.opf'] = str_replace('idref="c2"', 'idref="'.['img', 'nav', 'ncx', 'zzz', ''][mt_rand(0, 4)].'"', $opf);
            break;
        case 7: // binary chapter
            $entries['OEBPS/text/ch1.xhtml'] = random_bytes(mt_rand(0, 5000));
            break;
        case 8:
            $options['mimetype_compressed'] = true;
            break;
        case 9:
            $entries['mimetype'] = ['application/epub+zip'."\n", "\xEF\xBB\xBFapplication/epub+zip", '', 'application/epub+zip'.str_repeat(' ', 300)][mt_rand(0, 3)];
            break;
        case 10: // case-variant duplicate of an entry
            $name = $names[mt_rand(0, count($names) - 1)];
            $entries[strtoupper($name)] = $entries[$name] ?? '';
            break;
        case 11: // chapter body garbage
            $entries['OEBPS/text/sub/ch3.xhtml'] = [str_repeat('<p>', 20000), str_repeat('<span>', 10000).'x', '<p '.str_repeat('a="1" ', 3000).'>x</p>',
                '<img src="'.str_repeat('../', 5000).'x.png"/><img src="data:image/png;base64,AAAA"/><img src="images/cover.png%00.png"/>',
                "<p>\xC3\x28 \xFF</p>", "\xFF\xFE".mb_convert_encoding('<p>x</p>', 'UTF-16LE'), '<?xml version="1.0" encoding="utf-8"?><!DOCTYPE html [<!ENTITY a "b">]><p>&a;</p>',
                '<a href="  JaVa&#x0A;script:alert(1)">x</a><b onmouseover=alert(1)>x</b>', '<svg onload=alert(1)><script>x</script></svg>',
                str_repeat('<table><tr><td>', 3000)][mt_rand(0, 9)];
            break;
        case 12: // image corrupt / oversized / wrong type
            $entries['OEBPS/images/cover.png'] = [str_repeat('x', 2_500_000), "\x89PNG\r\n\x1a\n", '', random_bytes(100)][mt_rand(0, 3)];
            break;
        case 13: // container pointing elsewhere
            $entries['META-INF/container.xml'] = str_replace('OEBPS/content.opf', [' ', '../content.opf', 'OEBPS', 'OEBPS/text/ch1.xhtml', '/OEBPS/content.opf', 'nope'][mt_rand(0, 5)], $entries['META-INF/container.xml'] ?? '');
            break;
        case 14: // huge or odd entry names
            $entries[str_repeat('d/', 2000).'f'] = 'x';
            $entries["bad\x01name\xFF"] = 'x';
            break;
        default: // many spine items
            $entries['OEBPS/content.opf'] = str_replace('</manifest>', str_repeat('<item id="q" href="text/ch1.xhtml" media-type="application/xhtml+xml"/>', 500).'</manifest>', $opf);
    }
}

it('survives a deterministic fuzz of mutated EPUBs', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    mt_srand((int) (getenv('FUZZ_SEED') ?: 20260101));
    $allowed = ['reader.not_epub', 'reader.too_large', 'reader.zip_bomb', 'reader.invalid_epub'];

    set_error_handler(function (int $no, string $message, string $file, int $line) {
        if ((error_reporting() & $no) === 0) {
            return false; // deliberately silenced with @
        }

        throw new ErrorException($message, 0, $no, $file, $line);
    });

    $uploaded = 0;
    try {
        for ($case = 0; $case < 200; $case++) {
            $entries = fuzzBaseEntries();
            $options = [];
            for ($m = mt_rand(1, 3); $m > 0; $m--) {
                fuzzMutate(mt_rand(0, 15), $entries, $options);
            }
            $bytes = fuzzZip($entries, $options);

            $byteMutation = mt_rand(0, 3);
            if ($byteMutation === 0) {
                $bytes = substr($bytes, 0, mt_rand(0, strlen($bytes)));
            } elseif ($byteMutation === 1) {
                for ($f = mt_rand(1, 20); $f > 0; $f--) {
                    $bytes[mt_rand(0, strlen($bytes) - 1)] = chr(mt_rand(0, 255));
                }
            } elseif ($byteMutation === 2 && mt_rand(0, 4) === 0) {
                $bytes .= $bytes;
            }

            $path = tempnam(sys_get_temp_dir(), 'fuzz');
            file_put_contents($path, $bytes);
            $file = new UploadedFile($path, 'fuzz.epub', 'application/epub+zip', null, true);

            // The upload throttle is not what is under test.
            Cache::flush();
            $response = $this->actingAs($user)->post('/api/reader', ['file' => $file], ['Accept' => 'application/json']);
            $status = $response->getStatusCode();
            $label = "case {$case}";

            if ($status === 201) {
                $uploaded++;
                $document = ReaderDocument::query()->latest('id')->firstOrFail();
                for ($n = 0; $n < $document->chapters_count + 1; $n++) {
                    $chapter = $this->actingAs($user)->getJson("/api/reader/{$document->id}/chapters/{$n}");
                    expect($chapter->getStatusCode())->toBeIn([200, 404], "{$label} chapter {$n}");
                    if ($chapter->getStatusCode() === 200) {
                        $html = (string) $chapter->json('data.html');
                        expect(preg_match('/<script|\son\w+\s*=|javascript:/i', $html))->toBe(0, "{$label} chapter {$n}: {$html}");
                    }
                }
            } else {
                expect($status)->toBe(422, "{$label} status")
                    ->and($response->json('errors.file.0'))->toBeIn($allowed, $label);
            }

            unlink($path);
        }
    } finally {
        restore_error_handler();
    }

    expect($uploaded)->toBeGreaterThan(10);
});

describe('regressions found by fuzzing', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->upload = function (string $bytes) {
            $path = tempnam(sys_get_temp_dir(), 'fuzz');
            file_put_contents($path, $bytes);

            return $this->actingAs($this->user)->post('/api/reader', ['file' => new UploadedFile($path, 'r.epub', 'application/epub+zip', null, true)], ['Accept' => 'application/json']);
        };
    });

    it('answers invalid_epub, not a warning, for an entry with a bad CRC', function () {
        $bytes = fuzzZip(fuzzBaseEntries(), ['store_all' => true]);
        // Flip a byte of the stored data of the package: the CRC no longer matches.
        $offset = strpos($bytes, '<?xml version="1.0"?><package');
        $bytes[$offset + 30] = 'Z';
        expect($offset)->toBeInt();

        $response = ($this->upload)($bytes);
        expect($response->getStatusCode())->toBeIn([201, 422]);
        if ($response->getStatusCode() === 422) {
            $response->assertJsonPath('errors.file.0', 'reader.invalid_epub');
        }
    });

    it('survives any declared encoding of a chapter', function () {
        set_error_handler(function (int $no, string $message, string $file, int $line) {
            throw new ErrorException($message, 0, $no, $file, $line);
        });

        try {
            foreach (mb_list_encodings() as $encoding) {
                $entries = fuzzBaseEntries();
                $entries['OEBPS/text/ch1.xhtml'] = '<?xml version="1.0" encoding="'.$encoding.'"?><html><body><p>x</p><script>y</script></body></html>';
                $this->app->make(RateLimiter::class)->clear('uploads');
                Cache::flush();
                $response = ($this->upload)(fuzzZip($entries));
                $response->assertCreated();
                $document = ReaderDocument::query()->latest('id')->firstOrFail();
                $chapter = $this->actingAs($this->user)->getJson("/api/reader/{$document->id}/chapters/0");
                expect($chapter->getStatusCode())->toBeIn([200, 404], $encoding)
                    ->and((string) $chapter->json('data.html'))->not->toContain('<script');
            }
        } finally {
            restore_error_handler();
        }
    });

    it('rejects a package nested 10000 deep with a code', function () {
        $entries = fuzzReplace(fuzzBaseEntries(), 'OEBPS/content.opf', str_repeat('<a>', 10000));

        ($this->upload)(fuzzZip($entries))->assertUnprocessable()->assertJsonPath('errors.file.0', 'reader.invalid_epub');
    });

    it('serves a chapter nested 10000 deep without failing', function () {
        $entries = fuzzReplace(fuzzBaseEntries(), 'OEBPS/text/ch1.xhtml', '<html><body>'.str_repeat('<div>', 10000).'x</body></html>');

        ($this->upload)(fuzzZip($entries))->assertCreated();
        $this->actingAs($this->user)->getJson('/api/reader/'.ReaderDocument::query()->sole()->id.'/chapters/0')->assertOk();
    });

    it('imports a book whose broken navigation document has no titles', function () {
        $entries = fuzzReplace(fuzzBaseEntries(), 'OEBPS/nav.xhtml', str_repeat('<a>', 10000));
        $entries['OEBPS/toc.ncx'] = "<ncx>\xC3\x28</ncx>";

        ($this->upload)(fuzzZip($entries))->assertCreated();
    });

    it('rejects an archive cut at every twentieth offset', function () {
        $bytes = fuzzZip(fuzzBaseEntries());

        for ($offset = 0; $offset < strlen($bytes); $offset += max(1, intdiv(strlen($bytes), 20))) {
            $this->app->make(RateLimiter::class)->clear('uploads');
            Cache::flush();
            ($this->upload)(substr($bytes, 0, $offset))->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($code) => in_array($code, ['reader.not_epub', 'reader.invalid_epub'], true));
        }
    });
});
