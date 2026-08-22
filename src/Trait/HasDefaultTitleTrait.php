<?php

namespace App\Trait;

trait HasDefaultTitleTrait
{
    abstract public function getBodyContent(): string;

    abstract public function getDefaultComment();

    public function getDefaultTitle(): string
    {
        $lines = explode("\n", trim($this->getBodyContent()));
        $firstLine = trim(preg_replace('/[.,!:?;…—\-]+$/u', '', $lines[0]) ?? '');

        return '"' . $firstLine . '..."';
    }

    public function getDisplayTitle(): string
    {
        return preg_match('/\".*\.\.\.\"$/', $this->getTitle() ?? '')
            ? '* * *'
            : mb_strtoupper($this->getTitle() ?? '');
    }

    public function getDisplayComment(): string
    {
        return (string) $this->getComment();
    }

    private function ensureDefaults(): void
    {
        if (empty($this->getTitle())) {
            $this->setTitle($this->getDefaultTitle());
        }
        if (empty($this->getComment())) {
            $this->setComment($this->getDefaultComment());
        }
    }
}
