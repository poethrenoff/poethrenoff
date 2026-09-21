<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Work;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class StihiRuService
{
    private const string BASE_URL = 'https://stihi.ru';
    private const string LOGIN_URL = '/cgi-bin/login/intro.pl';
    private const string ADD_URL = '/login/page.html?add';
    private const string LIST_URL = '/login/page.html?list';
    private const string SAVE_URL = '/cgi-bin/login/page.pl';
    private const string USER_AGENT =
        'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.14 Safari/534.24';

    private ?string $cookies = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'STIHIRU_LOGIN')] private string $login,
        #[Autowire(env: 'STIHIRU_PASSWORD')] private string $password,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->login !== '' && $this->password !== '';
    }

    public function login(): void
    {
        $response = $this->request('POST', self::LOGIN_URL, [
            'body' => http_build_query([
                'login' => $this->login,
                'password' => $this->password,
            ]),
        ]);

        $cookies = [];
        foreach ($response->getHeaders(false)['set-cookie'] ?? [] as $cookieHeader) {
            if (preg_match('/^(login|pcode)=([^;]+)/', $cookieHeader, $matches)) {
                $cookies[$matches[1]] = $matches[2];
            }
        }

        if (!isset($cookies['login']) || !isset($cookies['pcode'])) {
            throw new \RuntimeException('Не удалось авторизоваться на stihi.ru: не получены cookies сессии');
        }

        $this->cookies = 'login=' . $cookies['login'] . '; pcode=' . $cookies['pcode'];
    }

    /**
     * Returns the top collection from the author's collection list along with
     * the number of works already published into it (the next offset).
     *
     * @return array{bookId: int, offset: int}
     */
    public function findDestinationCollection(): array
    {
        $html = $this->getUtf8(self::LIST_URL);

        if (
            !preg_match_all(
                '~<a href="/login/page\.html\?list&book=(\d+)"[^>]*>\s*<b>[^<]*</b>\s*</a></td>\s*'
                . '<td[^>]*>\s*<div class="topicname"[^>]*>\s*(\d+)\s*</div>~is',
                $html,
                $matches,
                PREG_SET_ORDER,
            )
        ) {
            throw new \RuntimeException('Не удалось найти сборники на странице ' . self::BASE_URL . self::LIST_URL);
        }

        return [
            'bookId' => (int) $matches[0][1],
            'offset' => (int) $matches[0][2],
        ];
    }

    /**
     * Publishes a single work into the given collection and returns its
     * public URL on stihi.ru.
     */
    public function publishWork(Work $work, int $bookId): string
    {
        $code = $this->fetchAddCode();

        $title = $this->toCp1251($work->getTitle());
        $text = $this->toCp1251($this->prepareText($work));

        $response = $this->request('POST', self::SAVE_URL, [
            'headers' => [
                'Referer' => self::BASE_URL . self::ADD_URL,
            ],
            'body' => http_build_query([
                'title' => $title,
                'text' => $text,
                'code' => $code,
                'block' => 'save',
                'text_topic' => '03',
                'dogovor' => 'on',
            ]),
        ]);

        $content = $response->getContent(false);

        if (
            !preg_match(
                '~<a href="/login/page\.html\?edit(?:&amp;|&)link=(\d{4}/\d{2}/\d{2}/\d+)"~i',
                $content,
                $matches,
            )
        ) {
            throw new \RuntimeException('Не удалось определить ссылку на опубликованное произведение');
        }

        $link = $matches[1];

        $this->request('GET', '/login/page.html?put&link=' . $link . '&to=' . $bookId);

        return self::BASE_URL . '/' . $link;
    }

    private function fetchAddCode(): string
    {
        $html = $this->getRaw(self::ADD_URL);

        if (!preg_match('~<input type="hidden" name="code" value="(\d+)"~', $html, $matches)) {
            throw new \RuntimeException('Не удалось получить code формы добавления произведения');
        }

        return $matches[1];
    }

    private function prepareText(Work $work): string
    {
        $text = preg_replace_callback(
            '/^ +| {2,}/m',
            static function (array $matches): string {
                return str_repeat('  ', strlen($matches[0]));
            },
            $work->getText(),
        );

        if ($text === null) {
            return '';
        }

        $comment = trim((string) $work->getComment());

        return rtrim($text) . ($comment !== '' ? "\r\n\r\n" . $comment : '');
    }

    /**
     * Converts a UTF-8 string to Windows-1251, refusing silently dropped
     * characters.
     */
    private function toCp1251(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1251', $text);
        if ($converted !== false) {
            return $converted;
        }

        $badCharacters = [];
        $length = mb_strlen($text, 'UTF-8');
        for ($index = 0; $index < $length; $index++) {
            $character = mb_substr($text, $index, 1, 'UTF-8');
            if (@iconv('UTF-8', 'Windows-1251', $character) === false) {
                $badCharacters[] = $character;
            }
        }

        $badCharacters = array_values(array_unique($badCharacters));
        $details = implode('', $badCharacters);

        throw new \RuntimeException(
            'Текст содержит символы, не представимые в кодировке Windows-1251'
            . ($details !== '' ? ': ' . $details : ''),
        );
    }

    private function getRaw(string $path): string
    {
        return $this->request('GET', $path)->getContent(false);
    }

    private function getUtf8(string $path): string
    {
        return mb_convert_encoding($this->getRaw($path), 'UTF-8', 'Windows-1251');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $options['headers'] = array_merge([
            'User-Agent' => self::USER_AGENT,
            'Accept-Language' => 'ru-RU,ru;q=0.9',
        ], $this->cookies !== null ? ['Cookie' => $this->cookies] : [], $options['headers'] ?? []);
        $options['timeout'] = 30;

        try {
            $response = $this->httpClient->request($method, self::BASE_URL . $path, $options);

            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException(
                    'Сервер stihi.ru вернул HTTP ' . $response->getStatusCode() . ' при запросе ' . $path,
                );
            }

            return $response;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Ошибка сети при запросе ' . self::BASE_URL . $path . ': ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
