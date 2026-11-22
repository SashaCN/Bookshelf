<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteType;
use App\Models\Quote;
use App\Models\UserBook;
use Generator;
use Illuminate\Database\Eloquent\Builder;

class ExportQuotesMarkdown
{
    private const CHUNK_SIZE = 200;

    /**
     * The given quotes as a Markdown document, produced piece by piece so a large collection never sits in memory.
     * Books come ordered by title, the quotes of a book oldest first.
     *
     * @param  Builder<Quote>  $quotes  the reader's (already filtered) quotes
     * @return Generator<int, string>
     */
    public function handle(Builder $quotes): Generator
    {
        yield "# Мої цитати\n";

        $bookIds = (clone $quotes)->reorder()->distinct()->pluck('user_book_id');

        if ($bookIds->isEmpty()) {
            yield "\nЦитат поки немає.\n";

            return;
        }

        $entries = UserBook::query()
            ->with('book.authors')
            ->whereIn('user_books.id', $bookIds)
            ->join('books', 'books.id', '=', 'user_books.book_id')
            ->orderBy('books.title')
            ->orderBy('user_books.id')
            ->select('user_books.*')
            ->get();

        foreach ($entries as $entry) {
            yield "\n## {$this->escape($entry->book->title)}\n";

            $authors = $entry->book->authors->pluck('name')->implode(', ');

            if ($authors !== '') {
                yield "\n_{$this->escape($authors)}_\n";
            }

            $bookQuotes = (clone $quotes)->reorder()->where('quotes.user_book_id', $entry->id)->lazyById(self::CHUNK_SIZE);

            foreach ($bookQuotes as $quote) {
                yield "\n{$this->render($quote)}\n";
            }
        }
    }

    private function render(Quote $quote): string
    {
        $lines = $this->lines($quote->content);

        $body = $quote->type === QuoteType::Insight
            ? '💡 '.implode("  \n", $lines)
            : implode("\n", array_map(fn (string $line) => rtrim("> {$line}"), $lines));

        $details = [];

        if ($quote->page !== null) {
            $details[] = "— с. {$quote->page}";
        }

        if ($quote->note !== null && trim($quote->note) !== '') {
            $details[] = 'Нотатка: '.implode("  \n", $this->lines($quote->note));
        }

        if ($quote->is_favorite) {
            $details[] = '★';
        }

        return $details === [] ? $body : $body."\n\n".implode("  \n", $details);
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        return array_map($this->escape(...), preg_split('/\R/u', trim($text)) ?: ['']);
    }

    /**
     * Makes user text inert: a line must not turn into a heading, quote, list item or code, and a trailing newline never leaks in.
     */
    private function escape(string $line): string
    {
        $line = str_replace('`', '\\`', trim($line));

        return (string) preg_replace(['/^([#>*+-])/', '/^(\d+)([.)])/'], ['\\\\$1', '$1\\\\$2'], $line);
    }
}
