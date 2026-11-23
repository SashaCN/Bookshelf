<?php

namespace Tests\Support;

/**
 * Small random chapters, the same every time: a generator of XHTML bodies with a fixed seed.
 *
 * They are made of the elements a book may have, nested at random (a cell in a cell, a row in a paragraph, text
 * loose in a table), and of text with the characters that tend to trip a browser up: carriage returns, non-breaking
 * spaces, emoji, Cyrillic, characters that must be escaped. The bodies are always well-formed XML; what the sanitizer
 * makes of them and what a browser makes of that is what the tests are about.
 */
final class ChapterFuzz
{
    private const TAGS = [
        'p', 'pre', 'table', 'tr', 'td', 'th', 'div', 'span', 'ul', 'li', 'br', 'blockquote', 'sub', 'sup', 'em', 'strong', 'h2', 'hr',
    ];

    private const TEXTS = [
        'word', 'Слово', '😀', '&#13;', '&#13;&#10;', '&nbsp;', '&amp;', '&lt;', '&gt;', 'x', ' ', '  ', 'a b', 'й', '&#10;', 'e&#769;',
        "\n", "\t", '&#13;x', 'l1&#13;&#10;l2&#13;l3', '«q»', '1 < 2'.'',
    ];

    private int $state;

    private function __construct(int $seed)
    {
        $this->state = $seed & 0xFFFFFFFF ?: 1;
    }

    /**
     * @return list<string> XHTML bodies
     */
    public static function bodies(int $count, int $seed): array
    {
        $fuzz = new self($seed);
        $bodies = [];

        for ($i = 0; $i < $count; $i++) {
            $body = '';

            for ($n = $fuzz->between(1, 3); $n > 0; $n--) {
                $body .= $fuzz->node(0);
            }

            $bodies[] = $body;
        }

        return $bodies;
    }

    private function next(): int
    {
        // xorshift32: no dependence on how a version of PHP seeds its own generators.
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;

        return $this->state = $x & 0xFFFFFFFF;
    }

    private function between(int $min, int $max): int
    {
        return $min + $this->next() % ($max - $min + 1);
    }

    /**
     * @param  list<string>  $choices
     */
    private function pick(array $choices): string
    {
        return $choices[$this->next() % count($choices)];
    }

    private function node(int $depth): string
    {
        if ($depth >= 5 || $this->between(0, 9) < 3) {
            return $this->pick(self::TEXTS);
        }

        $tag = $this->pick(self::TAGS);

        if ($tag === 'br' || $tag === 'hr') {
            return "<{$tag}/>";
        }

        $choices = match ($tag) {
            'table' => ['tr', 'tr', 'td', 'text', 'div', 'table', 'br'],
            'tr' => ['td', 'td', 'th', 'text', 'tr', 'p', 'br'],
            'td', 'th' => ['any', 'any', 'td', 'tr', 'th', 'table', 'text'],
            default => ['any', 'any', 'text'],
        };

        $inner = '';

        for ($n = $this->between(0, 4); $n > 0; $n--) {
            $choice = $this->pick($choices);
            $inner .= match ($choice) {
                'text' => $this->pick(self::TEXTS),
                'any' => $this->node($depth + 1),
                'br' => '<br/>',
                default => "<{$choice}>".$this->node($depth + 1)."</{$choice}>",
            };
        }

        return "<{$tag}>{$inner}</{$tag}>";
    }
}
