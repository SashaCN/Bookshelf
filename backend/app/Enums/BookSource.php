<?php

namespace App\Enums;

enum BookSource: string
{
    case OpenLibrary = 'openlibrary';
    case Manual = 'manual';
}
