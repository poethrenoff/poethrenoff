<?php

namespace App\Enum;

enum PoemStatus: string
{
    case Draft = 'draft';
    case Shortlist = 'shortlist';
    case Trash = 'trash';
}
