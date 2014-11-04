<?php
namespace Adminko\Valid;

class RequireValid extends Valid
{
    public function check($content)
    {
        return (string) $content !== '';
    }
}