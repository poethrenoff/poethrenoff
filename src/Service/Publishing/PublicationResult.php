<?php

namespace App\Service\Publishing;

final class PublicationResult
{
    private function __construct(
        private bool $success,
        private ?string $externalPostId,
        private ?string $externalUrl,
        private ?string $errorMessage,
    ) {
    }

    public static function success(string $externalPostId, string $externalUrl): self
    {
        return new self(true, $externalPostId, $externalUrl, null);
    }

    public static function error(string $errorMessage): self
    {
        return new self(false, null, null, $errorMessage);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getExternalPostId(): ?string
    {
        return $this->externalPostId;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
