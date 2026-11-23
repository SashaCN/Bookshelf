<?php

use App\Services\Epub\Text;

describe('length', function () {
    it('counts UTF-16 code units, like the length of a JavaScript string', function () {
        expect(Text::length(''))->toBe(0)
            ->and(Text::length('abc'))->toBe(3)
            ->and(Text::length('Привіт'))->toBe(6)
            ->and(Text::length('😀'))->toBe(2)
            ->and(Text::length('a😀b'))->toBe(4)
            ->and(Text::length('👨‍👩‍👧'))->toBe(8)
            ->and(Text::length("e\u{0301}"))->toBe(2);
    });
});

describe('slice', function () {
    it('cuts by UTF-16 code units', function () {
        expect(Text::slice('Привіт світе', 7, 5))->toBe('світе')
            ->and(Text::slice('a😀b', 1, 2))->toBe('😀')
            ->and(Text::slice('a😀b', 3, 1))->toBe('b')
            ->and(Text::slice('a😀b', 0, 4))->toBe('a😀b');
    });

    it('refuses a range that is not inside the text', function () {
        expect(Text::slice('abc', 2, 2))->toBeNull()
            ->and(Text::slice('abc', -1, 2))->toBeNull()
            ->and(Text::slice('abc', 0, 0))->toBeNull()
            ->and(Text::slice('', 0, 1))->toBeNull();
    });

    it('refuses a range that cuts an emoji in two', function () {
        expect(Text::slice('a😀b', 0, 2))->toBeNull()
            ->and(Text::slice('a😀b', 2, 2))->toBeNull()
            ->and(Text::slice('a😀b', 2, 1))->toBeNull();
    });
});

describe('ofHtml', function () {
    it('is the text of the markup this app writes', function () {
        expect(Text::ofHtml('<p>1 &lt; 2 &amp; 3 &gt; 0</p><p>a<br>b</p><hr>'))->toBe('1 < 2 & 3 > 0ab');
    });

    it('reads an escaped ampersand once', function () {
        expect(Text::ofHtml('<p>&amp;lt;</p>'))->toBe('&lt;');
    });
});

describe('collapse and skeleton', function () {
    it('collapses white space, non-breaking spaces included', function () {
        expect(Text::collapse("  a \n\t b\u{00A0}c  "))->toBe('a b c');
    });

    it('takes all white space out to compare two selections', function () {
        expect(Text::skeleton("one.\nTwo \u{00A0}three"))->toBe('one.Twothree');
    });
});
