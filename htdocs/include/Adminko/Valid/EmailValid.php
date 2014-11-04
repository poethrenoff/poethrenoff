<?php
namespace Adminko\Valid;

class EmailValid extends Valid
{
    public function check($content)
    {
        return (string) $content === '' || preg_match('/^[a-z0-9_\.-]+@[a-z0-9_\.-]+\.[a-z]{2,}$/i', $content);
    }
}