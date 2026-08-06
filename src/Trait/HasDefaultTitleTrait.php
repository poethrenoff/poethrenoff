<?php

namespace App\Trait;

trait HasDefaultTitleTrait
{
    abstract protected function getBodyContent(): string;

    abstract protected function getDefaultComment();

    public function getDefaultTitle(): string
    {
        $lines = explode("\n", trim($this->getBodyContent()));
        $firstLine = trim(preg_replace('/[.,!:?;…—\-]+$/u', '', $lines[0]));

        return '"' . $firstLine . '..."';
    }

    public function getDisplayTitle(): string
    {
        return preg_match('/\".*\.\.\.\"$/', $this->getTitle() ?? '')
            ? '* * *'
            : mb_strtoupper($this->getTitle() ?? '');
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
