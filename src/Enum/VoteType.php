<?php

namespace App\Enum;

enum VoteType: string
{
    case Like = 'like';
    case Dislike = 'dislike';
}
