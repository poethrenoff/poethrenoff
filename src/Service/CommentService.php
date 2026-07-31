<?php

namespace App\Service;

use App\Entity\BlogComment;
use App\Entity\WorkComment;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class CommentService
{
    public function __construct(
        private HtmlSanitizerInterface $htmlSanitizer,
    ) {
    }

    public function sanitizeContent(string $html): string
    {
        return $this->htmlSanitizer->sanitize($this->autolink($html));
    }

    /**
     * @param array<int, BlogComment|WorkComment> $flatComments
     * @return array<int, BlogComment|WorkComment>
     */
    public function buildCommentTree(array $flatComments): array
    {
        $byId = [];
        foreach ($flatComments as $comment) {
            $byId[$comment->getId()] = $comment;
        }

        foreach ($flatComments as $comment) {
            $parent = $comment->getParent();
            if ($parent !== null && isset($byId[$parent->getId()])) {
                $byId[$parent->getId()]->getChildren()->add($comment);
            }
        }

        return array_values(array_filter($flatComments, fn($c) => $c->getParent() === null));
    }

    public function validateAuthor(string $author): ?string
    {
        $author = trim($author);

        if (empty($author)) {
            return 'Имя обязательно';
        }

        if (mb_strlen($author) > 100) {
            return 'Имя не должно превышать 100 символов';
        }

        return null;
    }

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
