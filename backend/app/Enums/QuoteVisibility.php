<?php

namespace App\Enums;

enum QuoteVisibility: string
{
    /** Seen only by the reader. */
    case Private = 'private';

    /** Shown on the reader's public profile and in the feed of their followers. */
    case Public = 'public';
}
