<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * For commands that print what readers wrote (a note of a report, the text of a post). A reader controls those
 * strings, and a terminal obeys what it is sent: colours, a changed window title, text moved or hidden by escape
 * sequences, text made to read backwards. Only the printable text is left, on one line and cut short, and the
 * markup of the console itself (such as "<error>") is printed as it was written.
 */
trait PrintsUserText
{
    private function safe(?string $text, int $limit = 60): string
    {
        $text = mb_scrub((string) $text);

        // Escape sequences first (colours and cursor moves, window titles), so that none of them is left half-eaten.
        $text = preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]|\x1B\][^\x07\x1B]*(?:\x07|\x1B\\\\)?|\x1B[@-_]/', '', $text) ?? '';
        // Then any other control character, and the characters that change the direction of the text or hide it.
        $text = preg_replace('/[\x00-\x1F\x7F\x{80}-\x{9F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return OutputFormatter::escape(Str::limit($text, $limit));
    }
}
