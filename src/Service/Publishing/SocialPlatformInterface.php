<?php

namespace App\Service\Publishing;

use App\Entity\Poem;
use App\Enum\PublishPlatform;

interface SocialPlatformInterface
{
    public function key(): PublishPlatform;

    public function label(): string;

    public function iconName(): string;

    public function isConfigured(): bool;

    /**
     * Publish the given poem synchronously.
     */
    public function publish(Poem $poem): PublicationResult;
}
