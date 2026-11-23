<?php

use App\Enums\ReaderError;
use App\Services\Epub\EpubImportException;
use App\Services\Epub\EpubLimits;
use App\Services\Epub\HtmlSanitizer;
use App\Services\Epub\SafeXml;
use App\Services\Epub\SanitizedChapter;
use App\Services\Epub\Text;
use Tests\Support\EpubBuilder;

/**
 * The body of a chapter, sanitized.
 */
function sanitizeChapterBody(string $body, ?EpubLimits $limits = null): SanitizedChapter
{
    return sanitizeChapterDocument(EpubBuilder::xhtml($body), $limits);
}

function sanitizeChapterDocument(string $xml, ?EpubLimits $limits = null): SanitizedChapter
{
    $document = SafeXml::parse($xml);
    expect($document)->not->toBeNull();

    return (new HtmlSanitizer($limits ?? new EpubLimits))->sanitize($document);
}

function sanitizedChapterHtml(string $body): string
{
    return sanitizeChapterBody($body)->html;
}

describe('what is kept', function () {
    it('keeps the allowed elements and drops every attribute', function () {
        $html = sanitizedChapterHtml('<p class="big" id="x" style="color:red" onclick="alert(1)" data-x="1">Hello <em onmouseover="steal()">brave</em> <strong lang="en">new</strong> world</p>');

        expect($html)->toBe('<p>Hello <em>brave</em> <strong>new</strong> world</p>');
    });

    it('keeps every element of the list', function () {
        $html = sanitizedChapterHtml(
            '<h1>a</h1><h2>b</h2><h3>c</h3><h4>d</h4><h5>e</h5><h6>f</h6>'
            .'<blockquote>q</blockquote><ul><li>u</li></ul><ol><li>o</li></ol>'
            .'<p><i>i</i><b>b</b><u>u</u><sub>s</sub><sup>p</sup><code>c</code><span>s</span></p>'
            .'<pre>pre</pre><div>div</div><hr/>'
        );

        expect($html)->toBe(
            '<h1>a</h1><h2>b</h2><h3>c</h3><h4>d</h4><h5>e</h5><h6>f</h6>'
            .'<blockquote>q</blockquote><ul><li>u</li></ul><ol><li>o</li></ol>'
            .'<p><i>i</i><b>b</b><u>u</u><sub>s</sub><sup>p</sup><code>c</code><span>s</span></p>'
            .'<pre>pre</pre><div>div</div><hr>'
        );
    });

    it('keeps the text of elements it does not know and of links', function () {
        $html = sanitizedChapterHtml('<p><font color="red">red</font> <small>small</small> <a href="http://example.com/x">a link</a> <mark>marked</mark></p>');

        expect($html)->toBe('<p>red small a link marked</p>');
    });

    it('turns the containers of HTML5 books into plain blocks', function () {
        $html = sanitizedChapterHtml('<section epub:type="chapter"><header><h1>Title</h1></header><article><p>Text</p></article><aside>Note</aside></section>');

        expect($html)->toBe('<div><div><h1>Title</h1></div><div><p>Text</p></div><div>Note</div></div>');
    });

    it('keeps terms and their definitions apart', function () {
        expect(sanitizedChapterHtml('<dl><dt>Term</dt><dd>Meaning</dd></dl>'))->toBe('<div><div>Term</div><div>Meaning</div></div>');
    });

    it('keeps Cyrillic text and typographic characters', function () {
        $html = sanitizedChapterHtml('<p>«Привіт, світе» — сказав він…&nbsp;Їжак &amp; щука.</p>');

        expect($html)->toBe("<p>«Привіт, світе» — сказав він…\u{00A0}Їжак &amp; щука.</p>");
    });

    it('takes the text of the first heading', function () {
        $chapter = sanitizeChapterBody('<p>Intro</p><section><h2> Розділ <em>перший</em> </h2></section><h3>Later</h3>');

        expect($chapter->heading)->toBe('Розділ перший');
        expect(sanitizeChapterBody('<p>No heading</p>')->heading)->toBeNull();
    });
});

describe('what is dropped', function () {
    it('drops scripts, styles and the head with everything in them', function () {
        $html = sanitizedChapterHtml(
            '<script>alert(document.cookie)</script><p>kept</p><style>@import url(http://evil.example/x.css); p { color: red }</style>'
            .'<noscript><p>no script</p></noscript><template><p>template</p></template>'
        );

        expect($html)->toBe('<p>kept</p>');
    });

    it('drops frames, objects and media with what is inside', function () {
        $html = sanitizedChapterHtml(
            '<iframe src="javascript:alert(1)">fallback</iframe><object data="x.swf">object text</object><embed src="x"/>'
            .'<audio src="a.mp3">audio text</audio><video src="v.mp4">video text</video><canvas>canvas text</canvas><p>kept</p>'
        );

        expect($html)->toBe('<p>kept</p>');
    });

    it('drops svg and math with their content, whatever they do', function () {
        $html = sanitizedChapterHtml(
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><text>svg text</text></svg>'
            .'<p>before</p><math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi></math><p>after</p>'
        );

        expect($html)->toBe('<p>before</p><p>after</p>');
    });

    it('drops an element of a foreign namespace even when it has the name of an allowed one', function () {
        $html = sanitizedChapterHtml('<p>kept</p><foreign xmlns="http://www.w3.org/2000/svg"><p>hidden</p></foreign>');

        expect($html)->toBe('<p>kept</p>');
    });

    it('drops forms and their controls', function () {
        $html = sanitizedChapterHtml('<form action="http://evil.example/steal" method="post"><input name="q" value="x"/><button>Send</button><select><option>o</option></select><textarea>t</textarea></form><p>kept</p>');

        expect($html)->toBe('<p>kept</p>');
    });

    it('turns a link with a javascript address into its text', function () {
        $html = sanitizedChapterHtml('<p>Click <a href="javascript:alert(1)">here</a> or <a href="data:text/html,&lt;script&gt;alert(1)&lt;/script&gt;">there</a>.</p>');

        expect($html)->toBe('<p>Click here or there.</p>');
    });

    it('drops a picture, or says what the picture says when it has an alt text', function () {
        expect(sanitizedChapterHtml('<p>a<img src="x.png" onerror="alert(1)"/>b</p>'))->toBe('<p>ab</p>')
            ->and(sanitizedChapterHtml('<p>a<img src="x.png" alt=""/>b</p>'))->toBe('<p>ab</p>')
            ->and(sanitizedChapterHtml('<p>a<img src="x.png" alt="A cat"/>b</p>'))->toBe('<p>a A cat b</p>')
            ->and(sanitizedChapterHtml('<p><img src="x.png" onerror="alert(1)"/></p>'))->toBe('');
    });

    it('does not let a comment, a processing instruction or CDATA through', function () {
        $html = sanitizedChapterHtml('<p>a<!-- <script>alert(1)</script> --><?php echo 1; ?>b<![CDATA[<script>alert(2)</script>]]>c</p>');

        expect($html)->toBe('<p>ab&lt;script&gt;alert(2)&lt;/script&gt;c</p>');
    });

    it('keeps markup that is written as text as text', function () {
        $html = sanitizedChapterHtml('<p>&lt;script&gt;alert(1)&lt;/script&gt; &lt;img src=x onerror=alert(1)&gt;</p>');

        expect($html)->toBe('<p>&lt;script&gt;alert(1)&lt;/script&gt; &lt;img src=x onerror=alert(1)&gt;</p>');
    });

    it('never writes an attribute or an element that is not on the list, however hostile the input', function () {
        $html = sanitizedChapterHtml(
            '<p onclick="1" style="x" href="javascript:1" src="x" srcset="y" formaction="z">'
            .'<a href="javascript:1" onclick="2">a</a><img src="x" onerror="3"/><svg onload="4"/><details open="open" ontoggle="5">d</details>'
            .'<x:script xmlns:x="http://www.w3.org/1999/xhtml">alert(6)</x:script></p><body onload="7">body text</body>'
        );

        expect($html)->not->toMatch('/<\w+\s/')
            ->and($html)->not->toMatch('/<(?!\/?(?:p|div|br|hr|em|strong)\b)/')
            ->and($html)->not->toContain('=');
    });

    it('strips control characters', function () {
        $html = sanitizedChapterHtml("<p>a\u{0001}b\u{0008}c\u{007F}d</p>");

        expect($html)->not->toMatch('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/');
    });
});

describe('the shape of the result', function () {
    it('drops elements that hold no text', function () {
        $html = sanitizedChapterHtml('<div><p> </p><span></span><div><em></em><b> </b></div></div><p>kept</p><ul><li></li></ul><p><br/></p>');

        expect($html)->toBe('<p>kept</p>');
    });

    it('keeps a rule between blocks, even one in a wrapper', function () {
        expect(sanitizedChapterHtml('<p>a</p><hr/><p>b</p>'))->toBe('<p>a</p><hr><p>b</p>')
            ->and(sanitizedChapterHtml('<div><hr/></div>'))->toBe('<div><hr></div>');
    });

    it('collapses white space and trims it where a block begins and ends', function () {
        $html = sanitizedChapterHtml("\n  <p>  Hello \n\t  world  </p>\n  <p> next </p>\n");

        expect($html)->toBe('<p>Hello world</p><p>next</p>');
    });

    it('keeps the space between two inline elements', function () {
        expect(sanitizedChapterHtml('<p><em>a</em> <em>b</em></p>'))->toBe('<p><em>a</em> <em>b</em></p>');
    });

    it('trims white space around a line break and before a nested block', function () {
        expect(sanitizedChapterHtml('<p>one <br/> two</p>'))->toBe('<p>one<br>two</p>')
            ->and(sanitizedChapterHtml('<ul><li>item <ul><li>sub</li></ul></li></ul>'))->toBe('<ul><li>item<ul><li>sub</li></ul></li></ul>');
    });

    it('keeps white space in <pre> and drops only the newlines at its very start, which a browser would swallow', function () {
        $html = sanitizedChapterHtml("<pre>\n  indented\n    more\n</pre><pre><code>\nkept</code></pre><pre>\n\n\ntwo</pre>");

        expect($html)->toBe("<pre>  indented\n    more\n</pre><pre><code>\nkept</code></pre><pre>two</pre>");
    });

    it('gives a table only rows and a row only cells, so that no browser moves text out of it', function () {
        $html = sanitizedChapterHtml('<table><caption>Cap</caption><thead><tr><th>h</th></tr></thead><tbody><tr><td>a</td><td></td></tr><tr><td></td><td></td></tr></tbody>stray<tr>loose<td>b</td></tr></table>');

        expect($html)->toBe('<table><tr><td><div>Cap</div></td></tr><tr><th>h</th></tr><tr><td>a</td><td></td></tr><tr><td>stray</td></tr><tr><td>loose</td><td>b</td></tr></table>');
    });

    it('makes a row of cells that have no row', function () {
        expect(sanitizedChapterHtml('<table><td>a</td><td>b</td></table>'))->toBe('<table><tr><td>a</td><td>b</td></tr></table>');
    });

    it('copes with tables inside tables', function () {
        $html = sanitizedChapterHtml('<table><tr><td><table><tr><td><table><tr><td>deep</td></tr></table></td></tr></table></td></tr></table>');

        expect($html)->toBe('<table><tr><td><table><tr><td><table><tr><td>deep</td></tr></table></td></tr></table></td></tr></table>');
    });

    it('writes the same thing every time', function () {
        $body = '<div><p>One <em>two</em></p><table><tr><td>x</td></tr></table><pre>  p</pre><p>é😀</p></div>';

        expect(sanitizedChapterHtml($body))->toBe(sanitizedChapterHtml($body));
    });
});

describe('cells inside cells', function () {
    it('keeps only what a nested cell or row holds, so that a browser reads the same text', function (string $body, string $expected) {
        $chapter = sanitizeChapterBody($body);

        expect($chapter->html)->toBe($expected)
            ->and(Text::ofHtml($chapter->html))->toBe('abc');
    })->with([
        'a cell in a cell' => ['<table><tr><td>a<td>b</td>c</td></tr></table>', '<table><tr><td>abc</td></tr></table>'],
        'a row in a cell' => ['<table><tr><td>a<tr><td>b</td></tr>c</td></tr></table>', '<table><tr><td>abc</td></tr></table>'],
        'a header cell in a cell' => ['<table><tr><td>a<th>b</th>c</td></tr></table>', '<table><tr><td>abc</td></tr></table>'],
        'a cell in a header cell' => ['<table><tr><th>a<td>b</td>c</th></tr></table>', '<table><tr><th>abc</th></tr></table>'],
        'a row in a row' => ['<table><tr>a<tr><td>b</td></tr>c</tr></table>', '<table><tr><td>abc</td></tr></table>'],
        'a cell in a cell outside a table' => ['<td>a<td>b</td>c</td>', '<td>abc</td>'],
        'a row in a cell outside a table' => ['<td>a<tr><td>b</td></tr>c</td>', '<td>abc</td>'],
        'a header cell in a cell outside a table' => ['<td>a<th>b</th>c</td>', '<td>abc</td>'],
        'a cell in a cell, through an emphasis' => ['<table><tr><td>a<span><td>b</td></span>c</td></tr></table>', '<table><tr><td>a<span>b</span>c</td></tr></table>'],
        'a cell in a list in a cell' => ['<table><tr><td><ul><li>a<td>b</td></li>c</ul></td></tr></table>', '<table><tr><td><ul><li>ab</li>c</ul></td></tr></table>'],
        'a cell that is only there because a row has text of its own' => ['<table><tr><sub><th>a</th>b</sub>c</tr></table>', '<table><tr><td><sub>ab</sub>c</td></tr></table>'],
    ]);

    it('still keeps a table in a cell, which is a table of its own', function () {
        $html = sanitizedChapterHtml('<table><tr><td>a<table><tr><td>b</td></tr></table>c</td></tr></table>');

        expect($html)->toBe('<table><tr><td>a<table><tr><td>b</td></tr></table>c</td></tr></table>');
    });

    it('does not bring text to the start of a <pre> that a browser would then take a newline from', function () {
        $html = sanitizedChapterHtml('<table><tr><td><pre>&#13;<th>&#10;y</th></pre></td></tr></table>');

        expect($html)->toBe('<table><tr><td><pre>y</pre></td></tr></table>');
    });
});

describe('carriage returns', function () {
    it('are line feeds, in <pre> as well as outside of it, the way a browser reads them', function () {
        // A character reference is the only way one gets past the XML parser, which makes every line end a line feed.
        expect(sanitizedChapterHtml('<pre>l1&#13;&#10;l2&#13;l3</pre>'))->toBe("<pre>l1\nl2\nl3</pre>")
            ->and(sanitizedChapterHtml('<pre>x&#13;</pre>'))->toBe("<pre>x\n</pre>")
            ->and(sanitizedChapterHtml('<p>a&#13;b&#13;&#10;c</p>'))->toBe('<p>a b c</p>');
    });

    it('do not start a <pre>, where a browser would swallow the first newline', function () {
        expect(sanitizedChapterHtml('<pre>&#13;x</pre>'))->toBe('<pre>x</pre>')
            ->and(sanitizedChapterHtml('<pre>&#13;&#13;&#10;x</pre>'))->toBe('<pre>x</pre>');
    });

    it('never reach the text of a chapter', function () {
        $chapter = sanitizeChapterBody('<pre>a&#13;&#10;b&#13;c</pre><p>d&#13;e</p>');

        expect(Text::ofHtml($chapter->html))->not->toContain("\r")
            ->and($chapter->html)->not->toContain("\r")
            ->and($chapter->characters)->toBe(Text::length(Text::ofHtml($chapter->html)));
    });
});

describe('depth', function () {
    /** The deepest nesting of tags in a piece of the HTML we write. */
    function htmlNestingOf(string $html): int
    {
        $deepest = 0;
        $depth = 0;

        preg_match_all('#<(/?)(\w+)>#', $html, $tags, PREG_SET_ORDER);

        foreach ($tags as [, $closing, $name]) {
            if (in_array($name, ['br', 'hr'], true)) {
                continue;
            }

            $depth += $closing === '/' ? -1 : 1;
            $deepest = max($deepest, $depth);
        }

        return $deepest;
    }

    it('caps how deep the result nests and keeps the text', function () {
        $html = sanitizedChapterHtml(str_repeat('<div>', 150).'deep text'.str_repeat('</div>', 150));

        expect($html)->toContain('deep text')
            ->and(htmlNestingOf($html))->toBeLessThanOrEqual(34);
    });

    it('does not fall over a document nested five thousand levels deep', function () {
        $xml = EpubBuilder::xhtml('<p>before</p>'.str_repeat('<div>', 5000).'deep text'.str_repeat('</div>', 5000).'<p>after</p>');

        $chapter = sanitizeChapterDocument($xml);

        expect(htmlNestingOf($chapter->html))->toBeLessThanOrEqual(34)
            ->and($chapter->html)->toStartWith('<p>before</p>');
    });

    it('does not fall over five thousand inline elements in a row either', function () {
        $chapter = sanitizeChapterBody('<p>'.str_repeat('<em>x</em>', 5000).'</p>');

        expect($chapter->characters)->toBe(5000);
    });
});

describe('size', function () {
    it('measures the text in UTF-16 code units, the way a JavaScript string does', function () {
        expect(sanitizeChapterBody('<p>abc</p>')->characters)->toBe(3)
            ->and(sanitizeChapterBody('<p>Привіт</p>')->characters)->toBe(6)
            ->and(sanitizeChapterBody('<p>a😀b</p>')->characters)->toBe(4)
            ->and(sanitizeChapterBody('<p>1 &lt; 2 &amp; 3</p>')->characters)->toBe(9)
            ->and(sanitizeChapterBody('<p>one</p><p>two</p>')->characters)->toBe(6);
    });

    it('counts exactly the text that comes back out of the html', function () {
        $chapter = sanitizeChapterBody('<h1>Заголовок</h1><p>Текст з <em>виділенням</em>, емодзі 😀 і 1 &lt; 2 &amp; 3.</p><pre> x </pre>');

        expect(Text::length(Text::ofHtml($chapter->html)))->toBe($chapter->characters)
            ->and(Text::ofHtml($chapter->html))->toBe('ЗаголовокТекст з виділенням, емодзі 😀 і 1 < 2 & 3. x ');
    });

    it('refuses a chapter that is bigger than the limit once cleaned up', function () {
        $limits = new EpubLimits(maxChapterHtmlBytes: 1000);

        expect(fn () => sanitizeChapterBody('<p>'.str_repeat('a', 2500).'</p>', $limits))
            ->toThrow(EpubImportException::class);

        try {
            sanitizeChapterBody('<p>'.str_repeat('a', 2500).'</p>', $limits);
        } catch (EpubImportException $exception) {
            expect($exception->error)->toBe(ReaderError::ChapterTooLarge);
        }
    });

    it('does not count the white space that is collapsed against the limit', function () {
        $limits = new EpubLimits(maxChapterHtmlBytes: 1000);

        $chapter = sanitizeChapterBody('<p>'.str_repeat("a\n      ", 100).'</p>', $limits);

        expect($chapter->characters)->toBe(199);
    });

    it('counts every element of the document, also those whose tags are not kept', function () {
        // Links are unwrapped and keep only their text: no element of the output, but millions of them are time and memory.
        $xml = EpubBuilder::xhtml('<p>kept</p>'.str_repeat('<a/>', 300_000));

        expect(fn () => sanitizeChapterDocument($xml))->toThrow(EpubImportException::class, 'reader.chapter_too_large');
        expect(fn () => sanitizeChapterDocument(EpubBuilder::xhtml('<p>'.str_repeat('<font>x</font>', 300_000).'</p>')))
            ->toThrow(EpubImportException::class, 'reader.chapter_too_large');
    });

    it('counts the elements that are dropped with their content as well', function () {
        $xml = EpubBuilder::xhtml('<p>kept</p>'.str_repeat('<script/>', 300_000));

        expect(fn () => sanitizeChapterDocument($xml))->toThrow(EpubImportException::class, 'reader.chapter_too_large');
    });

    it('still takes a chapter with many inline elements that is within the limits', function () {
        $chapter = sanitizeChapterBody('<p>'.str_repeat('<a href="x">w</a> <span>o</span> ', 20_000).'</p>');

        expect($chapter->characters)->toBe(20_000 * 4 - 1);
    });

    it('refuses a chapter of millions of empty elements, which would only cost memory', function () {
        $xml = EpubBuilder::xhtml('<p>kept</p>'.str_repeat('<b></b>', 300_000));

        expect(fn () => sanitizeChapterDocument($xml))->toThrow(EpubImportException::class, 'reader.chapter_too_large');
    });

    it('handles a big chapter that is within the limit', function () {
        $paragraph = '<p>'.str_repeat('Слово ', 100).'</p>';
        $chapter = sanitizeChapterBody(str_repeat($paragraph, 1500));

        expect(strlen($chapter->html))->toBeGreaterThan(1_500_000)->toBeLessThan(2 * 1024 * 1024)
            ->and($chapter->characters)->toBe(1500 * 599);
    });
});
