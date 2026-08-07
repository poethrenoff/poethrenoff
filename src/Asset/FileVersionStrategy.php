<?php

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class FileVersionStrategy implements VersionStrategyInterface
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    public function getVersion(string $path): string
    {
        $file = $this->projectDir.'/public/'.$path;

        return is_file($file) ? (string) filemtime($file) : '';
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);

        return '' === $version ? $path : $path.'?v='.$version;
    }
}
