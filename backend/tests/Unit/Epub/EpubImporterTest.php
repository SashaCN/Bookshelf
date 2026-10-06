<?php

use App\Enums\ReaderError;
use App\Services\Epub\EpubImporter;
use App\Services\Epub\EpubImportException;
use App\Services\Epub\EpubLimits;
use App\Services\Epub\ParsedChapter;
use Tests\Support\EpubBuilder;

/**
 * @return list<ParsedChapter>
 */
function epubImportChapters(string $path, ?EpubLimits $limits = null): array
{
    $book = (new EpubImporter($limits ?? new EpubLimits))->open($path);

    try {
        return iterator_to_array($book->chapters(), false);
    } finally {
        $book->close();
    }
}

function epubImportFailure(string $path, ?EpubLimits $limits = null): ?ReaderError
{
    try {
        epubImportChapters($path, $limits);
    } catch (EpubImportException $exception) {
        return $exception->error;
    }

    return null;
}

describe('a good book', function () {
    it('reads the chapters in the order of the spine, with the titles of the table of contents', function () {
        $chapters = epubImportChapters(
            EpubBuilder::make()
                ->chapter('Перший розділ', '<p>Це початок.</p>')
                ->chapter('Chapter two', '<h1>Heading</h1><p>Second <em>one</em>.</p>')
                ->toFile(),
        );

        expect($chapters)->toHaveCount(2)
            ->and($chapters[0]->position)->toBe(0)
            ->and($chapters[0]->title)->toBe('Перший розділ')
            ->and($chapters[0]->html)->toBe('<p>Це початок.</p>')
            ->and($chapters[0]->characters)->toBe(11)
            ->and($chapters[1]->position)->toBe(1)
            ->and($chapters[1]->title)->toBe('Chapter two')
            ->and($chapters[1]->html)->toBe('<h1>Heading</h1><p>Second <em>one</em>.</p>');
    });

    it('reads the titles of an EPUB 2 book from its NCX', function () {
        $chapters = epubImportChapters(
            EpubBuilder::make()->withToc('ncx')->chapter('Розділ 1', '<p>a</p>')->chapter('Розділ 2', '<p>b</p>')->toFile(),
        );

        expect(array_map(fn (ParsedChapter $chapter) => $chapter->title, $chapters))->toBe(['Розділ 1', 'Розділ 2']);
    });

    it('takes the title of a chapter from its first heading when the book has no table of contents', function () {
        $chapters = epubImportChapters(
            EpubBuilder::make()->withToc('none')->chapter(null, '<h2>The Heading</h2><p>a</p>')->chapter(null, '<p>No heading here</p>')->toFile(),
        );

        expect($chapters)->toHaveCount(2)
            ->and($chapters[0]->title)->toBe('The Heading')
            ->and($chapters[1]->title)->toBeNull();
    });

    it('takes the title from the heading for a chapter the table of contents does not name', function () {
        $chapters = epubImportChapters(
            EpubBuilder::make()->chapter('Named', '<p>a</p>')->chapter(null, '<h1>From the text</h1><p>b</p>')->toFile(),
        );

        expect($chapters[0]->title)->toBe('Named')
            ->and($chapters[1]->title)->toBe('From the text');
    });

    it('skips the documents that have nothing to read', function () {
        $chapters = epubImportChapters(
            EpubBuilder::make()
                ->chapter('Cover', '<p><img src="cover.jpg" alt=""/></p>')
                ->chapter('Real', '<p>Text</p>')
                ->chapter('Blank', '<div> </div>')
                ->toFile(),
        );

        expect($chapters)->toHaveCount(1)
            ->and($chapters[0]->position)->toBe(0)
            ->and($chapters[0]->title)->toBe('Real');
    });

    it('skips spine items that do not exist and documents that are not in the spine', function () {
        $chapters = epubImportChapters(
            EpubBuilder::make()
                ->chapter('One', '<p>one</p>')
                ->document('Not in the spine', EpubBuilder::xhtml('<p>hidden</p>'), inSpine: false)
                ->danglingItemref('ghost')
                ->chapter('Two', '<p>two</p>')
                ->toFile(),
        );

        expect(array_map(fn (ParsedChapter $chapter) => $chapter->title, $chapters))->toBe(['One', 'Two']);
    });

    it('skips a spine item whose file is not in the archive', function () {
        $opf = '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0"><manifest>'
            .'<item id="a" href="a.xhtml" media-type="application/xhtml+xml"/>'
            .'<item id="gone" href="gone.xhtml" media-type="application/xhtml+xml"/>'
            .'<item id="b" href="b.xhtml" media-type="application/xhtml+xml"/>'
            .'</manifest><spine><itemref idref="a"/><itemref idref="gone"/><itemref idref="b"/></spine></package>';

        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf($opf)
            ->entry('OEBPS/a.xhtml', EpubBuilder::xhtml('<p>first</p>'))
            ->entry('OEBPS/b.xhtml', EpubBuilder::xhtml('<p>last</p>'))
            ->toFile();

        expect(array_map(fn (ParsedChapter $chapter) => $chapter->html, epubImportChapters($path)))->toBe(['<p>first</p>', '<p>last</p>']);
    });

    it('finds the chapters of a book laid out in directories, with links that are encoded', function () {
        $opf = '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" version="2.0"><manifest>'
            .'<item id="a" href="text/%D0%B3%D0%BB%D0%B0%D0%B2%D0%B0%201.xhtml" media-type="application/xhtml+xml"/>'
            .'<item id="img" href="images/c.jpg" media-type="image/jpeg"/>'
            .'<item id="ncx" href="toc/toc.ncx" media-type="application/x-dtbncx+xml"/>'
            .'</manifest><spine toc="ncx"><itemref idref="img"/><itemref idref="a"/><itemref idref="a"/></spine></package>';
        $ncx = '<?xml version="1.0"?><ncx xmlns="http://www.daisy.org/z3986/2005/ncx/"><navMap><navPoint id="n"><navLabel><text>Глава</text></navLabel>'
            .'<content src="../text/%D0%B3%D0%BB%D0%B0%D0%B2%D0%B0%201.xhtml#x"/></navPoint></navMap></ncx>';

        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf($opf)
            ->entry('OEBPS/text/глава 1.xhtml', EpubBuilder::xhtml('<p>Текст</p>'))
            ->entry('OEBPS/images/c.jpg', 'not really an image')
            ->entry('OEBPS/toc/toc.ncx', $ncx)
            ->toFile();

        $chapters = epubImportChapters($path);

        expect($chapters)->toHaveCount(1)
            ->and($chapters[0]->title)->toBe('Глава')
            ->and($chapters[0]->html)->toBe('<p>Текст</p>');
    });

    it('reads a chapter whose XHTML is not well-formed as far as it can', function () {
        $chapters = epubImportChapters(
            EpubBuilder::make()
                ->chapter('Fine', '<p>fine</p>')
                ->document('Broken', EpubBuilder::xhtml('<p>readable</p><p>unclosed <em>tag'))
                ->document('Not XML', 'this is not xml at all')
                ->chapter('After', '<p>after</p>')
                ->toFile(),
        );

        expect(array_map(fn (ParsedChapter $chapter) => $chapter->title, $chapters))->toBe(['Fine', 'Broken', 'After'])
            ->and($chapters[1]->html)->toContain('readable');
    });

    it('does not keep anything of a chapter in memory when it is done with it', function () {
        $builder = EpubBuilder::make();

        for ($i = 0; $i < 30; $i++) {
            $builder->chapter("Chapter {$i}", '<p>'.str_repeat('Текст розділу. ', 30_000).'</p>');
        }

        // Thirty chapters are more text than one text may have; that is not what is looked at here.
        $book = (new EpubImporter(new EpubLimits(maxTextCharacters: 100_000_000)))->open($builder->toFile());
        $before = memory_get_usage();
        $peak = 0;

        foreach ($book->chapters() as $chapter) {
            $peak = max($peak, memory_get_usage() - $before);
        }

        $book->close();

        // Thirty chapters of 400 KB each are 12 MB; reading them one by one costs a few chapters' worth, not all.
        expect($peak)->toBeLessThan(8 * 1024 * 1024);
    });
});

/**
 * A package whose manifest has an item for each of the given hrefs and whose spine has an itemref for each of them.
 *
 * @param  list<string>  $hrefs
 */
function packageListing(array $hrefs, string $extraManifest = ''): string
{
    $manifest = '';
    $spine = '';

    foreach ($hrefs as $i => $href) {
        $manifest .= "<item id=\"i{$i}\" href=\"{$href}\" media-type=\"application/xhtml+xml\"/>";
        $spine .= "<itemref idref=\"i{$i}\"/>";
    }

    return '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0">'
        ."<manifest>{$manifest}{$extraManifest}</manifest><spine>{$spine}</spine></package>";
}

/**
 * Case variants of one name: "chapter.xhtml", "Chapter.xhtml", "cHapter.xhtml" and so on, as many as asked for.
 *
 * @return list<string>
 */
function spellingsOf(string $name, int $count): array
{
    $letters = array_keys(array_filter(str_split($name), 'ctype_alpha'));
    $spellings = [];

    for ($mask = 0; count($spellings) < $count; $mask++) {
        $spelling = $name;

        foreach ($letters as $bit => $position) {
            if (($mask >> $bit) & 1) {
                $spelling[$position] = strtoupper($spelling[$position]);
            }
        }

        $spellings[] = $spelling;
    }

    return $spellings;
}

describe('one document, many spellings', function () {
    it('is one chapter, however many items of the spine are spelled differently from its file', function () {
        $spellings = spellingsOf('chapter.xhtml', 500);
        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf(packageListing($spellings))
            ->entry('OEBPS/chapter.xhtml', EpubBuilder::xhtml('<p>'.str_repeat('Слово ', 5000).'</p>'))
            ->toFile();

        $chapters = epubImportChapters($path);

        expect(count(array_unique($spellings)))->toBe(500)
            ->and(filesize($path))->toBeLessThan(20_000)
            ->and($chapters)->toHaveCount(1)
            ->and($chapters[0]->position)->toBe(0);
    });

    it('is one chapter when two items of the manifest lead to the same file', function () {
        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf(packageListing(['a.xhtml', 'a.xhtml', './a.xhtml', 'sub/../a.xhtml', 'A.xhtml']))
            ->entry('OEBPS/a.xhtml', EpubBuilder::xhtml('<p>only once</p>'))
            ->toFile();

        expect(array_map(fn (ParsedChapter $chapter) => $chapter->html, epubImportChapters($path)))->toBe(['<p>only once</p>']);
    });

    it('keeps its order among the other documents, at the place of its first mention', function () {
        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf(packageListing(['one.xhtml', 'Two.xhtml', 'ONE.xhtml', 'two.xhtml', 'three.xhtml']))
            ->entry('OEBPS/one.xhtml', EpubBuilder::xhtml('<p>one</p>'))
            ->entry('OEBPS/two.xhtml', EpubBuilder::xhtml('<p>two</p>'))
            ->entry('OEBPS/three.xhtml', EpubBuilder::xhtml('<p>three</p>'))
            ->toFile();

        expect(array_map(fn (ParsedChapter $chapter) => $chapter->html, epubImportChapters($path)))->toBe(['<p>one</p>', '<p>two</p>', '<p>three</p>']);
    });

    it('does not read the table of contents again for each spelling of it', function () {
        // The navigation document is listed 500 times, spelled differently, and has no links to give titles from: if each
        // of the 500 were read in turn, it would be 500 times its size.
        $nav = EpubBuilder::xhtml('<!-- '.str_repeat('padding ', 400).' --><nav epub:type="toc"><ol/></nav>');
        $manifest = '';

        foreach (spellingsOf('nav.xhtml', 500) as $i => $spelling) {
            $manifest .= "<item id=\"n{$i}\" href=\"{$spelling}\" media-type=\"application/xhtml+xml\" properties=\"nav\"/>";
        }

        $opf = packageListing(['ch1.xhtml'], $manifest);
        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf($opf)
            ->entry('OEBPS/nav.xhtml', $nav)
            ->entry('OEBPS/ch1.xhtml', EpubBuilder::xhtml('<h1>From the text</h1><p>text</p>'))
            ->toFile();

        // Room for the files of the archive and for reading each of them once, not for reading the navigation 500 times.
        $chapters = epubImportChapters($path, new EpubLimits(maxUnpackedBytes: strlen($opf) + strlen($nav) + 3000));

        expect($chapters)->toHaveCount(1)->and($chapters[0]->title)->toBe('From the text');
    });

    it('finds the title of a chapter although the link to it is spelled differently from its file', function () {
        $nav = EpubBuilder::xhtml('<nav epub:type="toc"><ol><li><a href="Ch1.XHTML">The title</a></li></ol></nav>');

        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf(packageListing(['CH1.xhtml'], '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>'))
            ->entry('OEBPS/nav.xhtml', $nav)
            ->entry('OEBPS/ch1.xhtml', EpubBuilder::xhtml('<p>text</p>'))
            ->toFile();

        expect(epubImportChapters($path)[0]->title)->toBe('The title');
    });
});

describe('a book of unreasonable size', function () {
    it('has a manifest with more than 5000 items, which is not a book', function () {
        $hrefs = array_map(fn (int $i) => "image{$i}.png", range(1, 5001));
        $opf = packageListing([], implode('', array_map(fn (string $href, int $i) => "<item id=\"m{$i}\" href=\"{$href}\" media-type=\"image/png\"/>", $hrefs, array_keys($hrefs))));

        $path = EpubBuilder::make()->withToc('none')->withOpf($opf)->entry('OEBPS/a.xhtml', EpubBuilder::xhtml('<p>x</p>'))->toFile();

        expect(epubImportFailure($path))->toBe(ReaderError::Invalid);
    });

    it('takes a manifest of exactly 5000 items', function () {
        $items = implode('', array_map(fn (int $i) => "<item id=\"m{$i}\" href=\"image{$i}.png\" media-type=\"image/png\"/>", range(2, 5000)));
        $opf = packageListing(['a.xhtml'], $items);

        $path = EpubBuilder::make()->withToc('none')->withOpf($opf)->entry('OEBPS/a.xhtml', EpubBuilder::xhtml('<p>x</p>'))->toFile();

        expect(epubImportChapters($path))->toHaveCount(1);
    });

    it('has a spine with more than 5000 items', function () {
        $opf = packageListing(array_fill(0, 5001, 'a.xhtml'));

        $path = EpubBuilder::make()->withToc('none')->withOpf($opf)->entry('OEBPS/a.xhtml', EpubBuilder::xhtml('<p>x</p>'))->toFile();

        expect(epubImportFailure($path))->toBe(ReaderError::Invalid);
    });

    it('has a navigation document with more than 5000 links', function () {
        $links = implode('', array_map(fn (int $i) => "<li><a href=\"ch1.xhtml#p{$i}\">Part {$i}</a></li>", range(1, 5001)));
        $nav = EpubBuilder::xhtml("<nav epub:type=\"toc\"><ol>{$links}</ol></nav>");

        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf(packageListing(['ch1.xhtml'], '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>'))
            ->entry('OEBPS/nav.xhtml', $nav)
            ->entry('OEBPS/ch1.xhtml', EpubBuilder::xhtml('<p>x</p>'))
            ->toFile();

        expect(epubImportFailure($path))->toBe(ReaderError::TooManyChapters);
    });

    it('has an NCX with more than 5000 points', function () {
        $points = implode('', array_map(fn (int $i) => "<navPoint id=\"p{$i}\"><navLabel><text>Part {$i}</text></navLabel><content src=\"ch1.xhtml#p{$i}\"/></navPoint>", range(1, 5001)));
        $ncx = '<?xml version="1.0"?><ncx xmlns="http://www.daisy.org/z3986/2005/ncx/"><navMap>'.$points.'</navMap></ncx>';

        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf(packageListing(['ch1.xhtml'], '<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>'))
            ->entry('OEBPS/toc.ncx', $ncx)
            ->entry('OEBPS/ch1.xhtml', EpubBuilder::xhtml('<p>x</p>'))
            ->toFile();

        expect(epubImportFailure($path))->toBe(ReaderError::TooManyChapters);
    });

    it('takes a navigation document with exactly 5000 links', function () {
        $links = implode('', array_map(fn (int $i) => "<li><a href=\"ch1.xhtml#p{$i}\">Part {$i}</a></li>", range(1, 5000)));
        $nav = EpubBuilder::xhtml("<nav epub:type=\"toc\"><ol>{$links}</ol></nav>");

        $path = EpubBuilder::make()
            ->withToc('none')
            ->withOpf(packageListing(['ch1.xhtml'], '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>'))
            ->entry('OEBPS/nav.xhtml', $nav)
            ->entry('OEBPS/ch1.xhtml', EpubBuilder::xhtml('<p>x</p>'))
            ->toFile();

        $chapters = epubImportChapters($path);

        expect($chapters)->toHaveCount(1)->and($chapters[0]->title)->toBe('Part 1');
    });

    it('has chapters with more text together than one text may have', function () {
        $builder = EpubBuilder::make();

        // 6 chapters of 1.5 million letters: 9 million, over the 8 million of the limit; 5 of them are not.
        for ($i = 0; $i < 6; $i++) {
            $builder->chapter("Chapter {$i}", '<p>'.str_repeat('abcdefghij', 150_000).'</p>');
        }

        expect(epubImportFailure($builder->toFile()))->toBe(ReaderError::TextTooLarge);

        $five = EpubBuilder::make();

        for ($i = 0; $i < 5; $i++) {
            $five->chapter("Chapter {$i}", '<p>'.str_repeat('abcdefghij', 150_000).'</p>');
        }

        expect(array_sum(array_map(fn (ParsedChapter $chapter) => $chapter->characters, epubImportChapters($five->toFile()))))->toBe(7_500_000);
    });

    it('counts the characters of the text against the limit exactly', function () {
        $builder = EpubBuilder::make()->chapter('A', '<p>'.str_repeat('a', 60).'</p>')->chapter('B', '<p>'.str_repeat('b', 40).'</p>');

        expect(epubImportChapters($builder->toFile(), new EpubLimits(maxTextCharacters: 100)))->toHaveCount(2)
            ->and(epubImportFailure($builder->toFile(), new EpubLimits(maxTextCharacters: 99)))->toBe(ReaderError::TextTooLarge);
    });
});

describe('a book that cannot be read', function () {
    it('is refused when it has no package', function () {
        expect(epubImportFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withContainer('<container><rootfiles/></container>')->toFile()))->toBe(ReaderError::Invalid)
            ->and(epubImportFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withContainer('not xml')->toFile()))->toBe(ReaderError::Invalid)
            ->and(epubImportFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withContainer('<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/missing.opf"/></rootfiles></container>')->toFile()))->toBe(ReaderError::Invalid);
    });

    it('is refused when the package is not XML or has no manifest or spine', function () {
        expect(epubImportFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withOpf('this is not XML')->toFile()))->toBe(ReaderError::Invalid)
            ->and(epubImportFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withOpf('<package xmlns="http://www.idpf.org/2007/opf"><spine/></package>')->toFile()))->toBe(ReaderError::Invalid)
            ->and(epubImportFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withoutSpine()->toFile()))->toBe(ReaderError::Invalid);
    });

    it('has no chapters when the spine is empty', function () {
        $builder = EpubBuilder::make()->document('Hidden', EpubBuilder::xhtml('<p>x</p>'), inSpine: false);

        expect(epubImportChapters($builder->toFile()))->toBe([]);
    });

    it('is refused when a document makes an entity declaration, however well hidden', function () {
        $xxe = '<?xml version="1.0"?><!DOCTYPE html [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><body><p>&xxe;</p></body></html>';

        expect(epubImportFailure(EpubBuilder::make()->chapter('Ok', '<p>ok</p>')->document('Evil', $xxe)->toFile()))->toBe(ReaderError::Invalid)
            ->and(epubImportFailure(EpubBuilder::make()->chapter('Ok', '<p>ok</p>')->withOpf(str_replace('<package', '<!DOCTYPE package [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><package', '<package xmlns="http://www.idpf.org/2007/opf"><manifest/><spine/></package>'))->toFile()))->toBe(ReaderError::Invalid);
    });

    it('does not put anything of the file system into the text', function () {
        $xxe = '<?xml version="1.0"?><!DOCTYPE html SYSTEM "file:///etc/passwd">'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><body><p>before &xxe; &passwd; after</p></body></html>';

        $chapters = epubImportChapters(EpubBuilder::make()->document('Evil', $xxe)->toFile());

        expect($chapters)->toHaveCount(1)
            ->and($chapters[0]->html)->toBe('<p>before after</p>')
            ->and($chapters[0]->html)->not->toContain('root:');
    });

    it('is refused when the book is protected by DRM', function () {
        $encryption = '<?xml version="1.0"?><encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:enc="http://www.w3.org/2001/04/xmlenc#">'
            .'<enc:EncryptedData><enc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes128-cbc"/>'
            .'<enc:CipherData><enc:CipherReference URI="OEBPS/ch1.xhtml"/></enc:CipherData></enc:EncryptedData></encryption>';

        expect(epubImportFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withEncryption($encryption)->toFile()))->toBe(ReaderError::Drm);
    });

    it('is not taken for DRM when the encryption only scrambles fonts', function () {
        $encryption = '<?xml version="1.0"?><encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:enc="http://www.w3.org/2001/04/xmlenc#">'
            .'<enc:EncryptedData><enc:EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/><enc:CipherData><enc:CipherReference URI="fonts/a.otf"/></enc:CipherData></enc:EncryptedData>'
            .'<enc:EncryptedData><enc:EncryptionMethod Algorithm="http://ns.adobe.com/pdf/enc#RC"/><enc:CipherData><enc:CipherReference URI="fonts/b.otf"/></enc:CipherData></enc:EncryptedData>'
            .'</encryption>';

        expect(epubImportChapters(EpubBuilder::make()->chapter('A', '<p>x</p>')->withEncryption($encryption)->toFile()))->toHaveCount(1);
    });

    it('is refused when it has more chapters than the limit', function () {
        $builder = EpubBuilder::make();

        for ($i = 0; $i < 4; $i++) {
            $builder->chapter("C{$i}", "<p>text {$i}</p>");
        }

        expect(epubImportFailure($builder->toFile(), new EpubLimits(maxChapters: 3)))->toBe(ReaderError::TooManyChapters)
            ->and(epubImportFailure($builder->toFile(), new EpubLimits(maxChapters: 4)))->toBeNull();
    });

    it('takes 500 chapters and refuses 501', function () {
        $builder = EpubBuilder::make();

        for ($i = 0; $i < 500; $i++) {
            $builder->chapter(null, "<p>text {$i}</p>");
        }

        expect(count(epubImportChapters($builder->toFile())))->toBe(500);

        $builder->chapter(null, '<p>one more</p>');

        expect(epubImportFailure($builder->toFile()))->toBe(ReaderError::TooManyChapters);
    });

    it('is refused when a chapter is too big once cleaned up', function () {
        $path = EpubBuilder::make()->chapter('Huge', '<p>'.str_repeat('Слово ', 500_000).'</p>')->toFile();

        expect(epubImportFailure($path))->toBe(ReaderError::ChapterTooLarge);
    });

    it('is refused when a document is too big to be read at all', function () {
        $path = EpubBuilder::make()->chapter('Big', '<p>'.str_repeat('x', 5000).'</p>')->toFile();

        expect(epubImportFailure($path, new EpubLimits(maxDocumentBytes: 3000)))->toBe(ReaderError::ChapterTooLarge);
    });
});
