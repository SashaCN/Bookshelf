<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Abuse = 'abuse';
    case Other = 'other';
}
