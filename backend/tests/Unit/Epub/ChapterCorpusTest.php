<?php

use App\Services\Epub\HtmlSanitizer;
use App\Services\Epub\SafeXml;
use App\Services\Epub\Text;
use Tests\Support\EpubBuilder;

/**
 * Awkward markup, and what the sanitizer makes of it: the HTML that goes to the browser, the text and the length that
 * offsets are counted in. The same file is copied to frontend/src/test/epubChapterCorpus.ts, where a test checks that a
 * browser reads the HTML as exactly that text. When the sanitizer is changed on purpose, run the cases again, write the
 * new answers to tests/fixtures/chapter-corpus.json, and copy them to the frontend.
 *
 * @return list<array{input: string, html: string, text: string, characters: int}>
 */
function epubChapterCorpus(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../../fixtures/chapter-corpus.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('writes the html, the text and the length that the browsers are tested against', function (array $case) {
    $document = SafeXml::parse(EpubBuilder::xhtml($case['input']));
    $chapter = (new HtmlSanitizer)->sanitize($document);

    expect($chapter->html)->toBe($case['html'])
        ->and(Text::ofHtml($chapter->html))->toBe($case['text'])
        ->and($chapter->characters)->toBe($case['characters'])
        ->and(Text::length($case['text']))->toBe($case['characters']);
})->with(fn () => array_map(fn (array $case) => [$case], epubChapterCorpus()));

it('has every kind of awkward case in the corpus', function () {
    expect(count(epubChapterCorpus()))->toBeGreaterThan(40);
});
