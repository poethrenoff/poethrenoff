<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Monster;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MonsterService
{
    private const string BASE_URL = 'https://stihi.ru';
    private const string USER_AGENT =
        'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.14 Safari/534.24';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Fetches the author page from stihi.ru and parses the number of works,
     * the displayed name and the date of the latest activity.
     *
     * @return array{author: string, poems: int, lastVisitDate: \DateTimeImmutable}
     *
     * @throws \RuntimeException when the page is unreachable or cannot be parsed
     */
    public function fetchAuthorData(string $login): array
    {
        $html = mb_convert_encoding($this->getRaw($login), 'UTF-8', 'Windows-1251');

        if (!preg_match('/Произведений:\s*<b>(\d+)<\/b>/', $html, $matches)) {
            throw new \RuntimeException('Не удалось получить количество произведений');
        }
        $poems = (int) $matches[1];

        if (!preg_match('/<h1>(.+?)<\/h1>/is', $html, $matches)) {
            throw new \RuntimeException('Не удалось получить имя автора');
        }
        $author = trim($matches[1]);

        if (!preg_match_all('/\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}/', $html, $matches)) {
            throw new \RuntimeException('Не удалось получить даты произведений');
        }

        $dates = [];
        foreach ($matches[0] as $match) {
            $date = \DateTimeImmutable::createFromFormat('d.m.Y H:i', $match);
            if ($date) {
                $dates[] = $date;
            }
        }

        if ($dates === []) {
            throw new \RuntimeException('Не удалось распарсить даты произведений');
        }

        usort($dates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $b <=> $a);

        return [
            'author' => $author,
            'poems' => $poems,
            'lastVisitDate' => $dates[0],
        ];
    }

    /**
     * Recomputes place_old/place across the whole rating ordered by the number
     * of poems (descending), like the legacy monster.php script did.
     *
     * @param list<Monster> $monsters
     */
    public function recalculatePlaces(array $monsters): void
    {
        usort($monsters, static fn (Monster $a, Monster $b): int => $b->getPoems() <=> $a->getPoems());

        $place = 0;
        foreach ($monsters as $monster) {
            $monster->setPlaceOld($monster->getPlace());
            $monster->setPlace(++$place);
        }
    }

    private function getRaw(string $login): string
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/avtor/' . rawurlencode($login), [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ],
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('Сервер stihi.ru вернул HTTP ' . $response->getStatusCode());
            }

            $content = $response->getContent(false);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Ошибка сети при запросе страницы автора: ' . $e->getMessage(), 0, $e);
        }

        if ($content === '') {
            throw new \RuntimeException('Страница недоступна');
        }

        return $content;
    }
}
