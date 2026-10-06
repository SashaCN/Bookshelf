<?php

namespace App\Enums;

enum ReportStatus: string
{
    /** Waiting for a moderator. */
    case Open = 'open';

    /** Looked at; it no longer counts towards hiding anything. */
    case Resolved = 'resolved';
}
