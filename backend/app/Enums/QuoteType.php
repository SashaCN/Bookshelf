<?php

namespace App\Enums;

enum QuoteType: string
{
    /** A passage copied from the book. */
    case Quote = 'quote';

    /** The reader's own thought, not a quotation. */
    case Insight = 'insight';
}
