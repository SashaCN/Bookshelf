<?php

use App\Enums\ReaderError;
use App\Services\Epub\EpubArchive;
use App\Services\Epub\EpubImportException;
use App\Services\Epub\EpubLimits;
use App\Services\Epub\EpubPaths;
use Tests\Support\EpubBuilder;

function archiveOpenFailure(string $path, ?EpubLimits $limits = null): ?ReaderError
{
    try {
        EpubArchive::open($path, $limits ?? new EpubLimits)->close();
    } catch (EpubImportException $exception) {
        return $exception->error;
    }

    return null;
}

function plainTestFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'epub');
    file_put_contents($path, $content);
    register_shutdown_function(static fn () => is_file($path) && unlink($path));

    return $path;
}

describe('a file that is not an EPUB', function () {
    it('is refused when it is not a ZIP archive', function (string $content) {
        expect(archiveOpenFailure(plainTestFile($content)))->toBe(ReaderError::NotEpub);
    })->with([
        'text' => ['This is just a text file, not a book. '.str_repeat('x', 100)],
        'empty' => [''],
        'html' => ['<html><body>'.str_repeat('x', 100).'</body></html>'],
        'a pdf' => ["%PDF-1.7\n".str_repeat('x', 200)],
        'a damaged archive' => ["PK\x03\x04".str_repeat("\x00", 40)],
    ]);

    it('is refused when the file does not exist', function () {
        expect(archiveOpenFailure('/nonexistent/book.epub'))->toBe(ReaderError::NotEpub);
    });

    it('is refused when it is a ZIP archive without a mimetype entry', function () {
        expect(archiveOpenFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withMimetype(null)->toFile()))->toBe(ReaderError::NotEpub);
    });

    it('is refused when its mimetype is another one', function (string $mimetype) {
        expect(archiveOpenFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withMimetype($mimetype)->toFile()))->toBe(ReaderError::NotEpub);
    })->with(['application/zip', 'application/vnd.oasis.opendocument.text', 'text/plain', '', 'application/epub+zip+evil']);

    it('is accepted with a newline after the mimetype', function () {
        expect(archiveOpenFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->withMimetype("application/epub+zip\n")->toFile()))->toBeNull();
    });

    it('is accepted when it is a proper EPUB', function () {
        expect(archiveOpenFailure(EpubBuilder::make()->chapter('A', '<p>x</p>')->toFile()))->toBeNull();
    });
});

describe('the names of the entries', function () {
    it('refuses an entry that leaves the archive', function (string $name) {
        $path = EpubBuilder::make()->chapter('A', '<p>x</p>')->entry($name, 'evil')->toFile();

        expect(archiveOpenFailure($path))->toBe(ReaderError::UnsafeArchive);
    })->with([
        'up and out' => ['../evil.txt'],
        'up and out, deep' => ['OEBPS/../../../etc/cron.d/evil'],
        'absolute' => ['/etc/passwd'],
        'absolute, one slash' => ['/evil.txt'],
        'a drive' => ['C:/Windows/evil.dll'],
        'backslashes' => ['..\\..\\evil.txt'],
        'backslash absolute' => ['\\evil.txt'],
        'only a parent' => ['..'],
    ]);

    it('accepts names that merely contain dots', function () {
        $path = EpubBuilder::make()->chapter('A', '<p>x</p>')->entry('OEBPS/chapter..1.xhtml', 'ok')->entry('OEBPS/.hidden', 'ok')->toFile();

        expect(archiveOpenFailure($path))->toBeNull();
    });

    it('recognizes a safe name', function (string $name, bool $safe) {
        expect(EpubPaths::isSafeEntryName($name))->toBe($safe);
    })->with([
        ['OEBPS/ch1.xhtml', true],
        ['META-INF/container.xml', true],
        ['a/b/c/', true],
        ['a..b/c', true],
        ['', false],
        ["a\0b", false],
        ['a/../b', false],
        ['../a', false],
        ['/a', false],
        ['c:\\a', false],
    ]);
});

describe('limits', function () {
    it('refuses a file that is bigger than the limit', function () {
        $path = EpubBuilder::make()->chapter('A', '<p>'.str_repeat('x', 5000).'</p>')->toFile();

        expect(archiveOpenFailure($path, new EpubLimits(maxFileBytes: 500)))->toBe(ReaderError::TooLarge);
    });

    it('refuses an archive with more entries than the limit', function () {
        $builder = EpubBuilder::make()->chapter('A', '<p>x</p>');

        for ($i = 0; $i < 20; $i++) {
            $builder->entry("images/{$i}.png", 'x');
        }

        expect(archiveOpenFailure($builder->toFile(), new EpubLimits(maxEntries: 15)))->toBe(ReaderError::TooManyEntries)
            ->and(archiveOpenFailure($builder->toFile(), new EpubLimits(maxEntries: 100)))->toBeNull();
    });

    it('refuses 2001 entries with the real limit and takes 2000', function () {
        $builder = EpubBuilder::make();

        for ($i = 0; $i < 1995; $i++) {
            $builder->entry("f/{$i}", '');
        }

        // The mimetype, container, package, navigation document and the chapter make five more: 2000 in all.
        $builder->chapter('A', '<p>x</p>');
        expect(archiveOpenFailure($builder->toFile()))->toBeNull();

        $builder->entry('f/one-too-many', '');
        expect(archiveOpenFailure($builder->toFile()))->toBe(ReaderError::TooManyEntries);
    });

    it('refuses a zip bomb: a small archive that says it unpacks to a huge entry', function () {
        $path = EpubBuilder::make()->chapter('A', '<p>x</p>')->largeEntry('OEBPS/bomb.bin', 70 * 1024 * 1024)->toFile();

        expect(filesize($path))->toBeLessThan(1024 * 1024)
            ->and(archiveOpenFailure($path))->toBe(ReaderError::TooLarge);
    });

    it('refuses many entries that together unpack to more than the limit', function () {
        $builder = EpubBuilder::make()->chapter('A', '<p>x</p>');

        for ($i = 0; $i < 7; $i++) {
            $builder->largeEntry("OEBPS/part{$i}.bin", 10 * 1024 * 1024);
        }

        expect(archiveOpenFailure($builder->toFile()))->toBe(ReaderError::TooLarge);
    });

    it('does not let an entry that lies about its size out of its limit', function () {
        $path = EpubBuilder::make()->chapter('A', '<p>x</p>')->entry('OEBPS/lie.xml', str_repeat('A', 100_000))->toFile();
        $bytes = file_get_contents($path);

        // In the central directory of the entry, say that it unpacks to 10 bytes.
        $header = strrpos($bytes, 'OEBPS/lie.xml') - 46;
        expect(substr($bytes, $header, 4))->toBe("PK\x01\x02");

        $forged = substr_replace($bytes, pack('V', 10), $header + 24, 4);
        file_put_contents($path, $forged);

        // The archive opens (its headers add up) and the lie is caught when the bytes come out, which are counted.
        $archive = EpubArchive::open($path, new EpubLimits);

        try {
            $archive->read('OEBPS/lie.xml', 1_000_000);
            $this->fail('Expected an exception.');
        } catch (EpubImportException $exception) {
            expect($exception->error)->toBe(ReaderError::Invalid);
        } finally {
            $archive->close();
        }
    });

    it('refuses an entry that is bigger than what the caller allows, before reading it', function () {
        $path = EpubBuilder::make()->chapter('A', '<p>x</p>')->entry('OEBPS/big.xml', str_repeat('A', 5000))->toFile();
        $archive = EpubArchive::open($path, new EpubLimits);

        try {
            $archive->read('OEBPS/big.xml', 1000, ReaderError::ChapterTooLarge);
            $this->fail('Expected an exception.');
        } catch (EpubImportException $exception) {
            expect($exception->error)->toBe(ReaderError::ChapterTooLarge);
        } finally {
            $archive->close();
        }
    });

    it('refuses an archive that is protected by a password', function () {
        $path = tempnam(sys_get_temp_dir(), 'epub');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('secret.xhtml', str_repeat('secret ', 20));
        $zip->setEncryptionName('secret.xhtml', ZipArchive::EM_AES_256, 'password');
        $zip->close();
        register_shutdown_function(static fn () => is_file($path) && unlink($path));

        expect(archiveOpenFailure($path))->toBe(ReaderError::Drm);
    });
});

describe('reading', function () {
    it('reads an entry, and says null for one that is not there', function () {
        $archive = EpubArchive::open(EpubBuilder::make()->chapter('A', '<p>x</p>')->toFile(), new EpubLimits);

        expect($archive->read('mimetype', 100))->toBe('application/epub+zip')
            ->and($archive->read('nothing.xml', 100))->toBeNull()
            ->and($archive->has('META-INF/container.xml'))->toBeTrue()
            ->and($archive->has('nothing.xml'))->toBeFalse();

        $archive->close();
    });

    it('finds a file whose name differs from the link to it in case', function () {
        $archive = EpubArchive::open(EpubBuilder::make()->chapter('A', '<p>x</p>')->toFile(), new EpubLimits);

        expect($archive->has('oebps/CH1.XHTML'))->toBeTrue()
            ->and($archive->read('oebps/CH1.XHTML', 100000))->toContain('<p>x</p>');

        $archive->close();
    });
});

describe('paths', function () {
    it('resolves a reference against a directory', function (string $base, string $reference, ?string $expected) {
        expect(EpubPaths::resolve($base, $reference))->toBe($expected);
    })->with([
        ['OEBPS', 'ch1.xhtml', 'OEBPS/ch1.xhtml'],
        ['OEBPS', 'text/ch1.xhtml#start', 'OEBPS/text/ch1.xhtml'],
        ['OEBPS/text', '../images/a.xhtml', 'OEBPS/images/a.xhtml'],
        ['', 'ch1.xhtml', 'ch1.xhtml'],
        ['OEBPS', './a/./b.xhtml', 'OEBPS/a/b.xhtml'],
        ['OEBPS', 'ch%201.xhtml', 'OEBPS/ch 1.xhtml'],
        ['OEBPS', '%D0%B3%D0%BB%D0%B0%D0%B2%D0%B0.xhtml', 'OEBPS/глава.xhtml'],
        ['OEBPS', 'ch1.xhtml?x=1', 'OEBPS/ch1.xhtml'],
        ['OEBPS', '..\\x.xhtml', 'x.xhtml'],
        ['OEBPS', '../../x.xhtml', null],
        ['', '../x.xhtml', null],
        ['OEBPS', '%2e%2e/%2e%2e/x.xhtml', null],
        ['OEBPS', '/etc/passwd', null],
        ['OEBPS', 'http://example.com/a.xhtml', null],
        ['OEBPS', 'file:///etc/passwd', null],
        ['OEBPS', 'javascript:alert(1)', null],
        ['OEBPS', '#top', null],
        ['OEBPS', '', null],
    ]);
});
