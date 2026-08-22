<?php

namespace App\Service\Publishing;

use App\Entity\Poem;
use App\Enum\PublishPlatform;
use App\Service\TelegramService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TelegramPlatform implements SocialPlatformInterface
{
    public function __construct(
        private TelegramService $telegramService,
        #[Autowire(env: 'TELEGRAM_CHAT_ID')] private string $chatId,
    ) {
    }

    public function key(): PublishPlatform
    {
        return PublishPlatform::Telegram;
    }

    public function label(): string
    {
        return 'Telegram';
    }

    public function iconName(): string
    {
        return 'telegram';
    }

    public function isConfigured(): bool
    {
        return $this->telegramService->isConfigured() && $this->chatId !== '';
    }

    public function publish(Poem $poem): PublicationResult
    {
        if (!$this->isConfigured()) {
            return PublicationResult::error('Telegram не настроен (нет моста или chat_id)');
        }

        $text = $this->telegramService->formatWork($poem);
        $messageId = $this->telegramService->publish($this->chatId, $text);

        if ($messageId === null) {
            return PublicationResult::error('Не удалось опубликовать стих в Telegram');
        }

        return PublicationResult::success(
            (string) $messageId,
            $this->buildMessageUrl($this->chatId, $messageId),
        );
    }

    private function buildMessageUrl(string $chatId, int $messageId): string
    {
        $identifier = ltrim($chatId, '@');

        if (is_numeric($identifier)) {
            $normalized = preg_replace('/^-?100/', '', $identifier);

            return 'https://t.me/c/' . $normalized . '/' . $messageId;
        }

        return 'https://t.me/' . $identifier . '/' . $messageId;
    }
}
