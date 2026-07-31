<?php

namespace App\Traits;

trait CommentUtilsTrait
{
    private function autolink(string $html): string
    {
        return preg_replace_callback(
            '/(<a[^>]*>.*?<\/a>)|(<[^>]+>)|(https?:\/\/[^\s<]+)/is',
            function ($matches) {
                if (!empty($matches[1])) {
                    return $matches[1];
                }
                if (!empty($matches[2])) {
                    return $matches[2];
                }
                if (!empty($matches[3])) {
                    $url = $matches[3];
                    return '<a href="' . $url . '">' . $url . '</a>';
                }
                return $matches[0];
            },
            $html
        ) ?? '';
    }
}
