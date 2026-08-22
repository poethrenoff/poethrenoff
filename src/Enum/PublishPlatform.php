<?php

namespace App\Enum;

enum PublishPlatform: string
{
    case Telegram = 'telegram';
    case Vk = 'vk';
    case LiveJournal = 'livejournal';
}
