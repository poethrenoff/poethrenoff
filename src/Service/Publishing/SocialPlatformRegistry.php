<?php

namespace App\Service\Publishing;

use App\Enum\PublishPlatform;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class SocialPlatformRegistry
{
    /**
     * @var array<string, SocialPlatformInterface>
     */
    private array $platforms = [];

    /**
     * @param iterable<SocialPlatformInterface> $platforms
     */
    public function __construct(
        #[AutowireIterator('app.social_platform')] iterable $platforms,
    ) {
        foreach ($platforms as $platform) {
            $this->platforms[$platform->key()->value] = $platform;
        }
    }

    public function get(PublishPlatform $platform): SocialPlatformInterface
    {
        return $this->platforms[$platform->value]
            ?? throw new \RuntimeException('Платформа не зарегистрирована: ' . $platform->value);
    }

    /**
     * @return list<SocialPlatformInterface>
     */
    public function all(): array
    {
        return array_values($this->platforms);
    }
}
