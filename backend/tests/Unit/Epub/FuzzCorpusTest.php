<?php

use App\Services\Epub\HtmlSanitizer;
use App\Services\Epub\SafeXml;
use App\Services\Epub\Text;
use Tests\Support\ChapterFuzz;
use Tests\Support\EpubBuilder;

/**
 * Random chapters (always the same ones) run through the real sanitizer: the HTML that goes to the browser, the text and
 * the length that offsets are counted in. The frontend has a copy of the fixture (frontend/src/test/fuzz-corpus.json) and
 * checks that a browser reads every one of the HTML strings as exactly that text.
 *
 * When the sanitizer is changed on purpose, write the new answers with
 *
 *     UPDATE_FUZZ_CORPUS=1 vendor/bin/pest tests/Unit/Epub/FuzzCorpusTest.php
 *
 * and copy tests/fixtures/fuzz-corpus.json to the frontend.
 */
const FUZZ_SEED = 20261006;
const FUZZ_COUNT = 600;
const FUZZ_FIXTURE = __DIR__.'/../../fixtures/fuzz-corpus.json';

/**
 * @return list<array{html: string, text: string, characters: int}>
 */
function fuzzedChapters(): array
{
    $chapters = [];

    foreach (ChapterFuzz::bodies(FUZZ_COUNT, FUZZ_SEED) as $body) {
        $document = SafeXml::parse(EpubBuilder::xhtml($body));
        $chapter = (new HtmlSanitizer)->sanitize($document);

        // Nothing is left of a chapter of white space only; such a chapter would not be a chapter.
        if ($chapter->characters > 0) {
            $chapters[] = ['html' => $chapter->html, 'text' => Text::ofHtml($chapter->html), 'characters' => $chapter->characters];
        }
    }

    return $chapters;
}

it('writes the chapters that the browsers are tested against', function () {
    $chapters = fuzzedChapters();

    if (getenv('UPDATE_FUZZ_CORPUS')) {
        file_put_contents(FUZZ_FIXTURE, json_encode($chapters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    $committed = json_decode((string) file_get_contents(FUZZ_FIXTURE), true, flags: JSON_THROW_ON_ERROR);

    expect($committed)->toBe($chapters)
        ->and(count($chapters))->toBeGreaterThan(300)
        ->and(filesize(FUZZ_FIXTURE))->toBeLessThan(300_000);
});

it('counts the text of every random chapter in UTF-16 code units, and never lets markup through', function () {
    foreach (fuzzedChapters() as $chapter) {
        expect(Text::length(Text::ofHtml($chapter['html'])))->toBe($chapter['characters'])
            ->and($chapter['html'])->not->toMatch('/<\w+\s/')
            ->and($chapter['text'])->not->toContain("\r");
    }
});
