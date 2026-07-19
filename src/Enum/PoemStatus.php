<?php

namespace App\Enum;

enum PoemStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Trash = 'trash';
}
