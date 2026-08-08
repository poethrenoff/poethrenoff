<?php

namespace App\Enum;

enum RecognizeTaskStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Recognizing = 'recognizing';
    case Recognized = 'recognized';
    case Formatting = 'formatting';
    case Completed = 'completed';
    case Error = 'error';
}
