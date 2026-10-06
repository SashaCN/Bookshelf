<?php

namespace App\Services\Epub;

use App\Enums\ReaderError;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Turns a chapter of a book (an XHTML document that somebody else wrote) into HTML that is safe to put into a page.
 *
 * Nothing is "cleaned" in place. The document is walked and a NEW tree is built from it, and only two kinds of things
 * are ever copied into it: text, and elements from a short list, without a single attribute. A script, an event
 * handler, a link, a style, a frame, a form, an image: there is nowhere in the new tree for any of them to go, so
 * whatever the document tries, it cannot reach the output. The output is later put into a page with `v-html`.
 *
 * The result is also what the offsets of quotes are measured in, so it has to come out the same way every time and
 * a browser has to read it back as the same text:
 * - white space is collapsed, and trimmed where a block begins or ends (but kept as it is inside <pre>);
 * - elements without text are dropped;
 * - a table holds only rows and a row only cells, as a browser would otherwise move text that sits elsewhere
 *   out in front of the table;
 * - the one newline a browser swallows after <pre> is not written.
 */
final class HtmlSanitizer
{
    /** How deep the output goes; an element below that loses its tags and keeps its content. */
    private const MAX_OUTPUT_DEPTH = 32;

    /** A document is not nested this deep (libxml stops at 256), this is a second wall. */
    private const MAX_INPUT_DEPTH = 300;

    /**
     * How many elements of a document are looked at, every one counted: the ones that are dropped, the ones that are
     * unwrapped (a link) and the ones that are empty as well. A chapter of the size the limits allow has far fewer; a
     * document of millions of empty elements would only cost memory and time.
     */
    private const MAX_ELEMENTS = 250_000;

    private const HEADING_LENGTH = 200;

    /**
     * The elements of a book and what they become. Everything that is not listed (links, <font>, <small> and the
     * like) is not copied, only the text inside it is.
     */
    private const TAGS = [
        'p' => 'p', 'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6',
        'blockquote' => 'blockquote', 'ul' => 'ul', 'ol' => 'ol', 'li' => 'li',
        'em' => 'em', 'strong' => 'strong', 'i' => 'i', 'b' => 'b', 'u' => 'u', 'sub' => 'sub', 'sup' => 'sup',
        'pre' => 'pre', 'code' => 'code', 'span' => 'span', 'div' => 'div',
        'table' => 'table', 'tr' => 'tr', 'td' => 'td', 'th' => 'th',
        // Containers of blocks that HTML5 books use.
        'section' => 'div', 'article' => 'div', 'aside' => 'div', 'figure' => 'div', 'figcaption' => 'div',
        'header' => 'div', 'footer' => 'div', 'main' => 'div', 'nav' => 'div', 'address' => 'div', 'hgroup' => 'div',
        'details' => 'div', 'summary' => 'div', 'center' => 'div', 'caption' => 'div',
        'dl' => 'div', 'dt' => 'div', 'dd' => 'div',
        // Inline elements that look like an allowed one.
        'cite' => 'em', 'dfn' => 'em', 'var' => 'em', 'tt' => 'code', 'kbd' => 'code', 'samp' => 'code',
    ];

    /** Elements that are dropped with everything inside them: nothing in there is text of the book. */
    private const DROPPED = [
        'script', 'style', 'head', 'title', 'meta', 'link', 'base', 'noscript', 'template',
        'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'param',
        'audio', 'video', 'source', 'track', 'canvas', 'map', 'area',
        'svg', 'math',
        'form', 'fieldset', 'legend', 'input', 'button', 'select', 'option', 'optgroup', 'datalist', 'textarea',
        'rt', 'rp',
    ];

    /** Elements of other languages that may sit inside an XHTML document: dropped with their content. */
    private const FOREIGN_NAMESPACES = [
        'http://www.w3.org/2000/svg',
        'http://www.w3.org/1998/Math/MathML',
    ];

    /** Elements that start a new line of text: white space next to them is not text. */
    private const BLOCKS = [
        'root', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'ul', 'ol', 'li', 'pre', 'div',
        'table', 'tr', 'td', 'th', 'hr',
    ];

    /** @var list<ElementNode> the elements that are open, the one text is being added to last */
    private array $stack = [];

    private int $preDepth = 0;

    private int $textBytes = 0;

    private int $elements = 0;

    public function __construct(private readonly EpubLimits $limits = new EpubLimits) {}

    /**
     * @throws EpubImportException when the chapter is too big
     */
    public function sanitize(DOMDocument $document): SanitizedChapter
    {
        $root = new ElementNode('root');
        $this->stack = [$root];
        $this->preDepth = 0;
        $this->textBytes = 0;
        $this->elements = 0;

        if ($document->documentElement !== null) {
            $this->copyElement($document->documentElement, 0);
        }

        $this->stack = [];
        $this->finish($root, false);
        $this->flattenCells($root, false);

        $html = '';
        $this->write($root, $html);

        if (strlen($html) > $this->limits->maxChapterHtmlBytes) {
            throw new EpubImportException(ReaderError::ChapterTooLarge);
        }

        return new SanitizedChapter($html, Text::length(Text::ofHtml($html)), $this->firstHeading($root));
    }

    private function copyChildren(DOMNode $parent, int $depth): void
    {
        for ($child = $parent->firstChild; $child !== null; $child = $child->nextSibling) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $this->addText($child->nodeValue ?? '');
            } elseif ($child instanceof DOMElement) {
                $this->copyElement($child, $depth + 1);
            }
            // Comments, processing instructions and entity references are not text of the book.
        }
    }

    private function copyElement(DOMElement $element, int $depth): void
    {
        if ($depth > self::MAX_INPUT_DEPTH) {
            $this->addText($element->textContent);

            return;
        }

        $this->countElement();
        $name = strtolower($element->localName);

        if (in_array($element->namespaceURI, self::FOREIGN_NAMESPACES, true) || in_array($name, self::DROPPED, true)) {
            return;
        }

        if ($name === 'img') {
            // A picture is not kept; what it says instead of itself is text.
            $alt = Text::collapse($element->getAttribute('alt'));

            if ($alt !== '') {
                $this->addText(" {$alt} ");
            }

            return;
        }

        if ($name === 'br' || $name === 'hr') {
            $this->addVoid($name);

            return;
        }

        $tag = self::TAGS[$name] ?? null;

        // A cell, a row inside a cell is not a table of its own: a browser would end the cell and move what follows it
        // out in front of the table. Only what is inside of them is kept.
        if ($tag === null || (($tag === 'td' || $tag === 'th' || $tag === 'tr') && $this->inCell())) {
            $this->copyChildren($element, $depth);

            return;
        }

        $opened = $this->open($tag);
        $this->copyChildren($element, $depth);

        if ($opened) {
            $this->close();
        }
    }

    private function open(string $tag): bool
    {
        if (count($this->stack) > self::MAX_OUTPUT_DEPTH) {
            return false;
        }

        $element = new ElementNode($tag);
        $this->stack[count($this->stack) - 1]->children[] = $element;
        $this->stack[] = $element;

        if ($tag === 'pre') {
            $this->preDepth++;
        }

        return true;
    }

    private function close(): void
    {
        $element = array_pop($this->stack);

        if ($element?->tag === 'pre') {
            $this->preDepth--;
        }
    }

    /**
     * Whether what is being copied is inside a cell of a table: the nearest of "table", "td" and "th" that is open is a cell.
     */
    private function inCell(): bool
    {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            $tag = $this->stack[$i]->tag;

            if ($tag === 'td' || $tag === 'th') {
                return true;
            }

            if ($tag === 'table') {
                return false;
            }
        }

        return false;
    }

    private function addVoid(string $tag): void
    {
        $element = new ElementNode($tag);
        $element->meaningful = $tag === 'hr';
        $this->stack[count($this->stack) - 1]->children[] = $element;
    }

    /**
     * @throws EpubImportException
     */
    private function countElement(): void
    {
        if (++$this->elements > self::MAX_ELEMENTS) {
            throw new EpubImportException(ReaderError::ChapterTooLarge, 'too many elements');
        }
    }

    /**
     * @throws EpubImportException
     */
    private function addText(string $text): void
    {
        // A browser reads a carriage return, alone or before a line feed, as a line feed. An XML parser leaves one that
        // is written as a character reference (&#13;) as it is.
        if (str_contains($text, "\r")) {
            $text = preg_replace('/\r\n?/', "\n", $text) ?? $text;
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\x{FFFE}|\x{FFFF}/u', '', $text) ?? '';

        if ($this->preDepth === 0) {
            $text = preg_replace('/[ \t\n\f\r]+/', ' ', $text) ?? '';
        }

        if ($text === '') {
            return;
        }

        // Stop early on a document that is plainly far too big; the exact limit is checked on the finished HTML.
        $this->textBytes += strlen($text);

        if ($this->textBytes > $this->limits->maxChapterHtmlBytes * 2) {
            throw new EpubImportException(ReaderError::ChapterTooLarge);
        }

        $parent = $this->stack[count($this->stack) - 1];
        $last = $parent->children[count($parent->children) - 1] ?? null;

        if ($last instanceof TextNode) {
            $last->value .= $text;
        } else {
            $parent->children[] = new TextNode($text);
        }
    }

    /**
     * Brings an element into its final shape, children first: drops what holds no text, joins and trims the text,
     * and gives tables the structure a browser expects. Sets `meaningful` on the element.
     */
    private function finish(ElementNode $element, bool $inPre): void
    {
        $inPre = $inPre || $element->tag === 'pre';
        $children = [];

        foreach ($element->children as $child) {
            if ($child instanceof ElementNode && $child->tag !== 'br' && $child->tag !== 'hr') {
                $this->finish($child, $inPre);

                // An empty cell is part of the shape of a table; every other empty element is nothing.
                if (! $child->meaningful && $child->tag !== 'td' && $child->tag !== 'th') {
                    // Except that "a<b> </b>b" is still two words: an element that held a space leaves the space.
                    $space = $this->onlyWhiteSpace($child);

                    if ($space === null) {
                        continue;
                    }

                    $child = new TextNode($space);
                }
            }

            $this->append($children, $child);
        }

        $children = $inPre ? $this->trimPre($element, $children) : $this->trimWhiteSpace($element, $children);

        if ($element->tag === 'table') {
            $children = $this->wrapInRows($children);
        } elseif ($element->tag === 'tr') {
            $children = $this->wrapInCells($children);
        }

        $element->children = $children;

        foreach ($children as $child) {
            // White space alone is not text of the book.
            if ($child instanceof TextNode ? trim($child->value, " \t\n\f\r") !== '' : $child->meaningful) {
                $element->meaningful = true;
                break;
            }
        }
    }

    /**
     * Adds a node to a list of children; text that follows text joins it.
     *
     * @param  list<ElementNode|TextNode>  $children
     */
    private function append(array &$children, ElementNode|TextNode $node): void
    {
        $last = $children === [] ? null : $children[count($children) - 1];

        if ($node instanceof TextNode && $last instanceof TextNode) {
            $last->value .= $node->value;
        } else {
            $children[] = $node;
        }
    }

    /**
     * The white space that is all a finished element holds (an inline element can be left with that), or null when
     * it holds nothing, or something else than text.
     */
    private function onlyWhiteSpace(ElementNode $element): ?string
    {
        $space = '';

        foreach ($element->children as $child) {
            if (! $child instanceof TextNode) {
                return null;
            }

            $space .= $child->value;
        }

        return $space === '' ? null : $space;
    }

    /**
     * In <pre> white space is text. Only the newlines at the very start go: a browser swallows the first one right after
     * <pre> and so would not show the text the way it is counted here.
     *
     * @param  list<ElementNode|TextNode>  $children
     * @return list<ElementNode|TextNode>
     */
    private function trimPre(ElementNode $element, array $children): array
    {
        $first = $children[0] ?? null;

        if ($element->tag === 'pre' && $first instanceof TextNode) {
            $first->value = ltrim($first->value, "\n");

            if ($first->value === '') {
                array_shift($children);
            }
        }

        return $children;
    }

    /**
     * Runs of white space are one space; there is none at the start or the end of a block, or next to a line
     * break or a block inside it; a text node left with nothing goes.
     *
     * @param  list<ElementNode|TextNode>  $children
     * @return list<ElementNode|TextNode>
     */
    private function trimWhiteSpace(ElementNode $element, array $children): array
    {
        $inBlock = in_array($element->tag, self::BLOCKS, true);
        $kept = [];

        foreach ($children as $i => $child) {
            if ($child instanceof ElementNode) {
                $kept[] = $child;

                continue;
            }

            $value = preg_replace('/[ \t\n\f\r]+/', ' ', $child->value) ?? $child->value;
            $previous = $children[$i - 1] ?? null;
            $next = $children[$i + 1] ?? null;

            if ($previous === null ? $inBlock : $this->endsLine($previous)) {
                $value = ltrim($value, ' ');
            }

            if ($next === null ? $inBlock : $this->endsLine($next)) {
                $value = rtrim($value, ' ');
            }

            if ($value !== '') {
                $child->value = $value;
                $kept[] = $child;
            }
        }

        return $kept;
    }

    private function endsLine(ElementNode|TextNode $node): bool
    {
        return $node instanceof ElementNode && ($node->tag === 'br' || in_array($node->tag, self::BLOCKS, true));
    }

    /**
     * A browser moves anything in a table that is not a row out in front of the table. Wrapping such things in a row
     * of their own keeps the text where it is.
     *
     * @param  list<ElementNode|TextNode>  $children
     * @return list<ElementNode|TextNode>
     */
    private function wrapInRows(array $children): array
    {
        $rows = [];
        $loose = [];

        foreach ($children as $child) {
            if ($child instanceof ElementNode && $child->tag === 'tr') {
                $this->flushLoose($loose, $rows, 'tr');
                $rows[] = $child;
            } else {
                $loose[] = $child;
            }
        }

        $this->flushLoose($loose, $rows, 'tr');

        return $rows;
    }

    /**
     * @param  list<ElementNode|TextNode>  $children
     * @return list<ElementNode|TextNode>
     */
    private function wrapInCells(array $children): array
    {
        $cells = [];
        $loose = [];

        foreach ($children as $child) {
            if ($child instanceof ElementNode && ($child->tag === 'td' || $child->tag === 'th')) {
                $this->flushLoose($loose, $cells, 'td');
                $cells[] = $child;
            } else {
                $loose[] = $child;
            }
        }

        $this->flushLoose($loose, $cells, 'td');

        return $cells;
    }

    /**
     * Puts what has piled up into a new element of its own: a row (which gets cells for whatever is not a cell) or a cell.
     *
     * @param  list<ElementNode|TextNode>  $loose
     * @param  list<ElementNode|TextNode>  $into
     */
    private function flushLoose(array &$loose, array &$into, string $tag): void
    {
        if ($loose === []) {
            return;
        }

        $wrapper = new ElementNode($tag);
        $wrapper->children = $tag === 'tr' ? $this->wrapInCells($loose) : $loose;
        $wrapper->meaningful = true;
        $into[] = $wrapper;
        $loose = [];
    }

    /**
     * Rows and cells are only for tables. A browser that finds one inside a cell, however deep (in a list, in an
     * emphasis, in a paragraph), ends the cell there and moves what follows to the front of the table. So in a cell
     * (down to a table of its own) a row or a cell is replaced by what is in it.
     */
    private function flattenCells(ElementNode $element, bool $inCell): void
    {
        $children = [];

        foreach ($element->children as $child) {
            if ($child instanceof ElementNode && $child->tag !== 'br' && $child->tag !== 'hr') {
                $isPart = $child->tag === 'tr' || $child->tag === 'td' || $child->tag === 'th';

                if ($inCell && $isPart) {
                    $this->flattenCells($child, true);

                    foreach ($child->children as $inner) {
                        $this->append($children, $inner);
                    }

                    continue;
                }

                $this->flattenCells($child, $child->tag === 'table' ? false : $inCell || $child->tag === 'td' || $child->tag === 'th');
            }

            $this->append($children, $child);
        }

        // Taking cells apart can bring text to the start of a <pre> that did not begin with it.
        $element->children = $element->tag === 'pre' ? $this->trimPre($element, $children) : $children;
    }

    private function write(ElementNode $element, string &$html): void
    {
        foreach ($element->children as $child) {
            if ($child instanceof TextNode) {
                $html .= htmlspecialchars($child->value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($child->tag === 'br' || $child->tag === 'hr') {
                $html .= "<{$child->tag}>";
            } else {
                $html .= "<{$child->tag}>";
                $this->write($child, $html);
                $html .= "</{$child->tag}>";
            }
        }
    }

    /**
     * The text of the first heading of the chapter, for a chapter the table of contents does not name.
     */
    private function firstHeading(ElementNode $element): ?string
    {
        foreach ($element->children as $child) {
            if (! $child instanceof ElementNode) {
                continue;
            }

            if (preg_match('/^h[1-6]$/', $child->tag) === 1) {
                $text = Text::collapse($this->textOf($child));

                if ($text !== '') {
                    return mb_substr($text, 0, self::HEADING_LENGTH);
                }
            }

            $found = $this->firstHeading($child);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function textOf(ElementNode $element): string
    {
        $text = '';

        foreach ($element->children as $child) {
            $text .= $child instanceof TextNode ? $child->value : $this->textOf($child);
        }

        return $text;
    }
}
