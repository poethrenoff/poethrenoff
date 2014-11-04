<?php
namespace Adminko\Valid;

class DateValid extends Valid
{
    public function parse_check($content)
    {
        return (string) $content === '' || preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $content, $match) && 
            checkdate ($match[2], $match[1], $match[3]);
    }
    
    public function check($content)
    {
        return (string) $content === '' || preg_match('/^(\d{4})(\d{2})(\d{2})000000$/', $content, $match) && 
            checkdate ($match[2], $match[3], $match[1]);
    }
}