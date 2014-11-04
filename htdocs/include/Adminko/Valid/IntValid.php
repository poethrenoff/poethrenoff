<?php
namespace Adminko\Valid;

class IntValid extends Valid
{
    public function check($content)
    {
        return (string) $content === '' || preg_match('/^\-?\+?\d+$/', $content);
    }
}