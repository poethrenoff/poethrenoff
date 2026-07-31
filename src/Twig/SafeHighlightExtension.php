<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SafeHighlightExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('safe_highlight', [$this, 'safeHighlight'], ['is_safe' => ['html']]),
        ];
    }

    public function safeHighlight(string $text, string $query): string
    {
        $text = htmlspecialchars(strip_tags($text), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        $words = array_values(array_filter(explode(' ', $query)));

        foreach ($words as $word) {
            $escapedWord = preg_quote(
                htmlspecialchars($word, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
                '/'
            );
            $replaced = preg_replace('/(' . $escapedWord . ')/iu', '<b>$1</b>', $text);

            if ($replaced !== null) {
                $text = $replaced;
            }
        }

        return $text;
    }
}
