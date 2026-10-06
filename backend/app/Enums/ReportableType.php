<?php

namespace App\Enums;

/**
 * What a report can be about. The value is what the "reports" table stores and what the moderation commands accept
 * (the same names are registered as the morph map in AppServiceProvider).
 */
enum ReportableType: string
{
    case Post = 'post';
    case Comment = 'comment';
}
