<?php

namespace App\Service;

use App\Entity\Work;
use App\Repository\WorkRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramService
{
    private const string WELCOME_MESSAGE =
        'Отправьте боту слово или фразу из стихотворения, которое хотите найти. ' .
        'Если таких стихотворений окажется несколько, бот выберет из них одно на свой вкус. ' .
        'Команда /random возвращает случайное стихотворение.';

    public function __construct(
        private WorkRepository $workRepository,
        #[Autowire(env: 'TELEGRAM_BRIDGE_URL')] private string $bridgeUrl,
        #[Autowire(env: 'TELEGRAM_BOT_SECRET')] private string $botSecret,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Compute bot replies for an update without any direct Telegram access.
     *
     * @param array<string, mixed> $update raw Telegram update payload
     *
     * @return list<string>
     */
    public function computeReplies(array $update): array
    {
        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return [];
        }

        $text = $message['text'] ?? null;
        if (!is_string($text)) {
            return ['Отправьте текстовое сообщение'];
        }

        $text = trim($text);

        if ($text === '/start' || $text === '/help') {
            return [self::WELCOME_MESSAGE];
        }

        if ($text === '/random') {
            $work = $this->workRepository->findRandomActiveFromFavorites();
            if ($work !== null) {
                return [$this->formatWork($work)];
            }

            return ['В избранном пока нет стихотворений'];
        }

        $searchPagination = $this->workRepository->search($text, 1, 100, true);
        $items = $searchPagination->getItems();
        $works = is_array($items) ? $items : iterator_to_array($items);

        if ($works === []) {
            return ['По запросу "<b>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</b>" ничего не найдено'];
        }

        return [$this->formatWork($works[array_rand($works)])];
    }

    public function formatWork(Work $work): string
    {
        $title = htmlspecialchars($work->getDisplayTitle(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = htmlspecialchars($work->getText(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $comment = htmlspecialchars($work->getComment() ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $lines = [];
        if ($title !== '* * *') {
            $lines[] = "<b>{$title}</b>";
            $lines[] = '';
        }
        $lines[] = $text;
        if ($comment) {
            $lines[] = '';
            $lines[] = "<i>{$comment}</i>";
        }

        return implode("\n", $lines);
    }

    /**
     * Push a message to Telegram through the AWS bridge daemon.
     *
     * @param int|string $chatId Telegram chat or channel id (@username or numeric id)
     */
    public function publish(int|string $chatId, string $text): bool
    {
        $options = [
            'timeout' => 15,
            'headers' => [
                'X-Bot-Secret' => $this->botSecret,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'chat_id' => $chatId,
                'text' => $text,
            ]),
        ];

        try {
            $response = $this->httpClient->request('POST', $this->bridgeUrl, $options);

            return $response->getStatusCode() < 400;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
