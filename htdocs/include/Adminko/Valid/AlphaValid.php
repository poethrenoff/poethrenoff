<?php
namespace Adminko\Valid;

class AlphaValid extends Valid
{
    public function parse_check($content)
    {
        return (string) $content === '' || preg_match('/^[a-z0-9_]+$/i', $content);
    }
    
    public function check($content)
    {
        return (string) $content === '' || preg_match('/^[a-z0-9_]+$/i', $content);
    }
}