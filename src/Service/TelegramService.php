<?php

namespace App\Service;

use App\Entity\Poem;
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
     * @param array<string, mixed> $update
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

    /**
     * @param Work|Poem $work
     * @return string
     */
    public function formatWork(Work|Poem $work): string
    {
        $title = htmlspecialchars($work->getDisplayTitle(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = htmlspecialchars($work->getBodyContent(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $comment = htmlspecialchars($work->getDisplayComment(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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

    public function isConfigured(): bool
    {
        return $this->bridgeUrl !== '';
    }

    /**
     * @param int|string $chatId
     * @param string $text
     * @return int|null
     *
     */
    public function publish(int|string $chatId, string $text): ?int
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

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            $data = json_decode($response->getContent(false), true);

            return is_array($data) && isset($data['message_id']) && is_int($data['message_id'])
                ? $data['message_id']
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
