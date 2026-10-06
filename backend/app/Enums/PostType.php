<?php

namespace App\Enums;

enum PostType: string
{
    /** A snapshot of a passage from the author's own quotes, with an optional comment. */
    case Quote = 'quote';

    /** A rating of a book, with an optional text. */
    case Review = 'review';
}
