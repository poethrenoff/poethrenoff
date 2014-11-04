<?php
namespace Adminko\Valid;

class DatetimeValid extends Valid
{
    public function parse_check($content)
    {
        return (string) $content === '' || preg_match('/^(\d{2})\.(\d{2})\.(\d{4}) (\d{2})\:(\d{2})$/', $content, $match) && 
            checkdate ($match[2], $match[1], $match[3]) && ($match[4] >= 0 && $match[4] <= 23 && $match[5] >= 0 && $match[5] <= 59);
    }
    
    public function check($content)
    {
        return (string) $content === '' || preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $content, $match) && 
            checkdate ($match[2], $match[3], $match[1]) && ($match[4] >= 0 && $match[4] <= 23 && $match[5] >= 0 && $match[5] <= 59 && $match[6] >= 0 && $match[6] <= 59);
    }
}