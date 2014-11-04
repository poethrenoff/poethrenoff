<?php
namespace Adminko\Valid;

class FloatValid extends Valid
{
    public function check($content)
    {
        return (string) $content === '' || preg_match('/^\-?\+?\d+[\.,]?\d*$/', $content);
    }
}