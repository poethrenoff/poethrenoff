<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Monster;
use App\Repository\MonsterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Scans the stihi.ru daily stream for authors active within a period, checks
 * their poem counts and adds new "monsters" (authors with at least 5000
 * published works) to the Monster table.
 *
 * The run is a per-date pipeline: for every date of the range the daily stream
 * pages are read, the authors seen that day are checked, everything learned is
 * committed, and only then the date is marked as done. A date is therefore the
 * unit of both progress and resume: an interrupted run loses at most the
 * current date, never the dates already committed.
 *
 * HTTP work is done through a bounded worker pool on top of
 * HttpClientInterface::stream() (curl multi, single process). Retryable
 * answers are re-queued with an exponential backoff instead of blocking the
 * pool, and repeated failures temporarily shrink the pool (adaptive throttling
 * against the site's anti-DDoS protection).
 *
 * @phpstan-type ScrapeTask array{key: string, url: string, attempt: int, data: array<string, mixed>}
 * @phpstan-type DiscoverResult array{
 *     datesTotal: int,
 *     datesDone: int,
 *     streamPages: int,
 *     streamFailed: int,
 *     authorsSeen: int,
 *     authorsChecked: int,
 *     failedChecks: int,
 *     newMonsters: list<Monster>,
 *     pendingDates: list<string>,
 *     stopped: bool
 * }
 */
class MonsterDiscoveryService
{
    private const string BASE_URL = 'https://stihi.ru';
    private const string AUTHOR_URL = '/avtor/%s';
    private const string USER_AGENT =
        'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.14 Safari/534.24';

    public const int MIN_POEMS = 5000;

    private const int MAX_RETRIES = 6;
    private const int PAGE_SIZE = 30;
    private const int MIN_CONCURRENCY = 2;
    private const int LOOKUP_CHUNK = 400;
    /** Checked authors buffered before an intermediate write. */
    private const int SCAN_STATE_BATCH = 500;

    /** Consecutive retryable answers that halve the pool. */
    private const int ERROR_BURST = 6;
    /** Consecutive successful answers that grow the pool back by one. */
    private const int RECOVER_AFTER = 60;
    /** Pause taken by the whole pool after a burst of retryable answers, seconds. */
    private const float COOLDOWN = 5.0;
    /** Idle slice of a single stream() pass, seconds. */
    private const float STREAM_TICK = 1.0;
    /** Hard deadline for a single request before it is force-retried, seconds. */
    private const float STALL_TIMEOUT = 90.0;

    /** @var list<int> */
    private const array RETRYABLE_STATUS = [408, 429, 500, 502, 503, 504];

    private \PDO $db;

    private int $concurrency = 12;
    private int $activeConcurrency = 12;
    private int $recheckDays = 0;
    private bool $dryRun = false;

    private int $errorStreak = 0;
    private int $successStreak = 0;
    private float $coolDownUntil = 0.0;

    private bool $stopRequested = false;

    private float $startTime = 0.0;
    private float $datesDoneAtStart = 0.0;
    private ?float $lastProgressAt = null;

    private int $datesTotal = 0;
    private int $datesDone = 0;
    private int $dateIndex = 0;
    private string $currentDate = '';

    private int $streamPages = 0;
    private int $streamFailed = 0;
    private int $authorsSeen = 0;
    private int $authorsChecked = 0;
    private int $failedChecks = 0;

    private int $datePagesTotal = 0;
    private int $datePagesDone = 0;
    private int $datePagesFailed = 0;
    private int $dateAuthorsTotal = 0;
    private int $dateAuthorsDone = 0;
    private int $dateAuthorsFailed = 0;
    private bool $dateCounted = false;
    /** 0 while the stream pages of the date are read, 1 once authors are checked. */
    private int $datePhase = 0;

    /** @var list<Monster> */
    private array $newMonsters = [];

    private int $committedMonsters = 0;

    /** @var array<string, true> */
    private array $monsterLogins = [];

    /** @var list<array{login: string, author: string, poems: ?int, checkedAt: string}> */
    private array $scanBuffer = [];

    /** @var list<ScrapeTask> */
    private array $poolQueue = [];

    /** @var list<array{at: float, task: ScrapeTask}> */
    private array $poolDeferred = [];

    /** @var array<string, ResponseInterface> */
    private array $poolInflight = [];

    /** @var array<string, array{task: ScrapeTask, startedAt: float}> */
    private array $poolMeta = [];

    /** @var callable(string): void|null */
    private $progress = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private MonsterRepository $monsterRepository,
        private MonsterService $monsterService,
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'kernel.project_dir')] private string $projectDir,
    ) {
    }

    /**
     * Asks the running discovery to stop as soon as possible. Everything
     * learned so far is committed; the date being processed stays unfinished
     * and is picked up again by the next run.
     */
    public function requestStop(): void
    {
        $this->stopRequested = true;
    }

    /**
     * Runs the discovery for the given date range, one date at a time.
     *
     * @param callable(string): void|null $progress
     * @return DiscoverResult
     */
    public function discover(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        int $concurrency,
        int $recheckDays,
        bool $dryRun,
        bool $resetState,
        ?callable $progress = null,
    ): array {
        $this->concurrency = max(1, $concurrency);
        $this->activeConcurrency = $this->concurrency;
        $this->recheckDays = max(0, $recheckDays);
        $this->dryRun = $dryRun;
        $this->progress = $progress;
        $this->stopRequested = false;
        $this->startTime = microtime(true);
        $this->lastProgressAt = null;
        $this->streamPages = 0;
        $this->streamFailed = 0;
        $this->authorsSeen = 0;
        $this->authorsChecked = 0;
        $this->failedChecks = 0;
        $this->newMonsters = [];
        $this->committedMonsters = 0;
        $this->errorStreak = 0;
        $this->successStreak = 0;
        $this->coolDownUntil = 0.0;
        $this->db = $this->openStateDb($resetState);
        $this->monsterLogins = $this->existingMonsterLogins();

        $dates = $this->dateRange($from, $to);
        $this->datesTotal = count($dates);

        $pending = [];
        foreach ($dates as $date) {
            if (!$this->isStreamDateDone($date)) {
                $pending[] = $date;
            }
        }

        $this->datesDone = $this->datesTotal - count($pending);
        $this->datesDoneAtStart = (float) $this->datesDone;
        $this->dateIndex = $this->datesDone;

        try {
            foreach ($pending as $date) {
                if ($this->stopRequested) {
                    break;
                }

                $this->beginDate($date);
                $complete = $this->processDate($date);
                $this->commitDate($date, $complete);
            }
        } finally {
            $this->flushScanState();
            $this->commitMonsters();
            $this->reportProgress(true);
        }

        return [
            'datesTotal' => $this->datesTotal,
            'datesDone' => $this->datesDone,
            'streamPages' => $this->streamPages,
            'streamFailed' => $this->streamFailed,
            'authorsSeen' => $this->authorsSeen,
            'authorsChecked' => $this->authorsChecked,
            'failedChecks' => $this->failedChecks,
            'newMonsters' => $this->newMonsters,
            'pendingDates' => $this->pendingDates($dates),
            'stopped' => $this->stopRequested,
        ];
    }

    private function beginDate(string $date): void
    {
        $this->currentDate = $date;
        $this->dateIndex++;
        $this->datePagesTotal = 0;
        $this->datePagesDone = 0;
        $this->datePagesFailed = 0;
        $this->dateAuthorsTotal = 0;
        $this->dateAuthorsDone = 0;
        $this->dateAuthorsFailed = 0;
        $this->dateCounted = false;
        $this->datePhase = 0;
        $this->reportProgress(true);
    }

    /**
     * Scans one date end to end: stream pages -> unique authors -> poem counts.
     *
     * @return bool true when the date was covered without transient failures
     *              and may be marked as done
     */
    private function processDate(string $date): bool
    {
        $collected = [];
        $complete = $this->collectDateAuthors($date, $collected);
        $this->authorsSeen += count($collected);

        if ($this->stopRequested) {
            return false;
        }

        $complete = $this->checkAuthors($collected) && $complete;

        return $complete && !$this->stopRequested;
    }

    /**
     * Phase A of a date: reads the first stream page to learn how many pages
     * the day has, then reads the rest, collecting unique author logins.
     *
     * @param array<string, string> $collected login => display name
     *
     * @phpstan-impure
     */
    private function collectDateAuthors(string $date, array &$collected): bool
    {
        $complete = true;

        $tasks = [$this->task('s:' . $date . ':0', $this->streamUrl($date, 0), ['date' => $date, 'page' => 0])];
        $this->datePagesTotal = 1;

        $this->runPool(
            $tasks,
            function (array $task, string $html) use (&$collected, $date): array {
                $this->collectStreamAuthors($html, $collected);
                $this->streamPages++;
                $this->datePagesDone++;

                if ((int) $task['data']['page'] !== 0) {
                    return [];
                }

                $extra = [];
                $max = $this->parseDayMaxPoemNumber($html, $date);
                for ($start = $max - self::PAGE_SIZE; $start > 0; $start -= self::PAGE_SIZE) {
                    $extra[] = $this->task(
                        's:' . $date . ':' . $start,
                        $this->streamUrl($date, $start),
                        ['date' => $date, 'page' => $start],
                    );
                }
                $this->datePagesTotal += count($extra);

                return $extra;
            },
            function (array $task, bool $permanent, string $reason) use (&$complete): void {
                $this->streamFailed++;
                $this->datePagesFailed++;
                $this->datePagesDone++;
                if (!$permanent) {
                    $complete = false;
                }
            },
        );

        return $complete;
    }

    /**
     * Phase B of a date: checks the poem counts of the authors seen that day,
     * skipping the ones already known as monsters or checked recently, and
     * registers everyone reaching MIN_POEMS.
     *
     * @param array<string, string> $collected login => display name
     *
     * @phpstan-impure
     */
    private function checkAuthors(array $collected): bool
    {
        $complete = true;
        $tasks = [];

        // Numeric logins come back from array_keys() as integers.
        $logins = array_map(strval(...), array_keys($collected));

        foreach ($this->pendingLogins($logins) as $login) {
            $tasks[] = $this->task(
                'a:' . $login,
                self::BASE_URL . sprintf(self::AUTHOR_URL, rawurlencode($login)),
                ['login' => $login, 'name' => $collected[$login]],
            );
        }

        $this->dateAuthorsTotal = count($tasks);
        $this->datePhase = 1;

        if ($tasks === []) {
            return true;
        }

        $this->runPool(
            $tasks,
            function (array $task, string $html): array {
                $login = (string) $task['data']['login'];
                $this->dateAuthorsDone++;

                try {
                    $parsed = $this->monsterService->parseAuthorHtml($html);
                } catch (\RuntimeException) {
                    // The page answered but is not a readable author page
                    // (deleted, blocked, redesigned). Remember the attempt so
                    // the login is not retried until the TTL expires.
                    $this->failedChecks++;
                    $this->dateAuthorsFailed++;
                    $this->rememberAuthor($login, (string) $task['data']['name'], null);

                    return [];
                }

                $this->authorsChecked++;
                $this->rememberAuthor($login, $parsed['author'], $parsed['poems']);

                if ($parsed['poems'] >= self::MIN_POEMS) {
                    $this->registerMonster($login, $parsed);
                }

                return [];
            },
            function (array $task, bool $permanent, string $reason) use (&$complete): void {
                $this->failedChecks++;
                $this->dateAuthorsDone++;
                $this->dateAuthorsFailed++;
                if ($permanent) {
                    $this->rememberAuthor(
                        (string) $task['data']['login'],
                        (string) $task['data']['name'],
                        null,
                    );

                    return;
                }
                $complete = false;
            },
        );

        return $complete;
    }

    /**
     * Commits everything learned for a date and, when the date was covered in
     * full, marks it as done so the next run skips it.
     */
    private function commitDate(string $date, bool $complete): void
    {
        $this->flushScanState();
        $this->commitMonsters();

        if (!$complete) {
            return;
        }

        // A dry run scans the date for real, it just must not remember it.
        if (!$this->dryRun) {
            $this->markDateDone($date);
        }

        $this->datesDone++;

        // The date is now counted in full, so its own share must stop
        // contributing to the percentage while its numbers stay on screen.
        $this->dateCounted = true;

        $this->checkpoint();
    }

    /**
     * Filters the logins of a day down to the ones that still need a request:
     * not a known monster and not checked within the recheck TTL.
     *
     * @param list<string> $logins
     * @return list<string>
     */
    private function pendingLogins(array $logins): array
    {
        $candidates = [];
        foreach ($logins as $login) {
            if (!isset($this->monsterLogins[$login])) {
                $candidates[] = $login;
            }
        }

        if ($candidates === []) {
            return [];
        }

        $cutoff = (new \DateTimeImmutable('now'))
            ->modify(sprintf('-%d days', $this->recheckDays))
            ->format('Y-m-d H:i:s');

        $fresh = [];
        foreach (array_chunk($candidates, self::LOOKUP_CHUNK) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->db->prepare(
                'SELECT login FROM scan_state WHERE checked_at >= ? AND login IN (' . $placeholders . ')',
            );
            $stmt->execute(array_merge([$cutoff], $chunk));
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $login) {
                $fresh[(string) $login] = true;
            }
        }

        return array_values(array_filter($candidates, static fn (string $login): bool => !isset($fresh[$login])));
    }

    /**
     * @param array{author: string, poems: int, lastVisitDate: \DateTimeImmutable} $parsed
     */
    private function registerMonster(string $login, array $parsed): void
    {
        if (isset($this->monsterLogins[$login])) {
            return;
        }

        $this->monsterLogins[$login] = true;

        $monster = new Monster();
        $monster->setLogin($login);
        $monster->setAuthor($parsed['author']);
        $monster->setPoems($parsed['poems']);
        $monster->setPoemsOld($parsed['poems']);
        $monster->setLastVisitDate($parsed['lastVisitDate']);
        $monster->setIsActive(true);

        $this->newMonsters[] = $monster;

        if (!$this->dryRun) {
            $this->entityManager->persist($monster);
        }
    }

    /**
     * Writes the monsters found since the previous commit and renumbers the
     * rating. Called after every date so an interrupted run keeps its finds.
     */
    private function commitMonsters(): void
    {
        if ($this->dryRun || count($this->newMonsters) === $this->committedMonsters) {
            return;
        }

        $this->entityManager->flush();

        $all = $this->monsterRepository->findOrderedByPoems();
        $this->monsterService->recalculatePlaces($all);
        $this->entityManager->flush();

        $this->committedMonsters = count($this->newMonsters);
    }

    // ---------------------------------------------------------------- pool --

    /**
     * @param array<string, mixed> $data
     * @return ScrapeTask
     */
    private function task(string $key, string $url, array $data): array
    {
        return ['key' => $key, 'url' => $url, 'attempt' => 0, 'data' => $data];
    }

    /**
     * Runs a bounded-concurrency HTTP pool over the given tasks.
     *
     * Retryable answers are re-queued with an exponential backoff instead of
     * sleeping inside the stream loop, so a slow retry never stalls the other
     * workers. Every response is explicitly consumed or cancelled before it is
     * dropped: a Symfony response released while still un-initialized checks
     * its status code from the destructor, which is how a single 502 used to
     * abort the whole command from an arbitrary place.
     *
     * @param list<ScrapeTask> $tasks
     * @param callable(ScrapeTask, string): list<ScrapeTask> $onSuccess receives the UTF-8 body
     * @param callable(ScrapeTask, bool, string): void $onFailure receives (task, permanent, reason)
     */
    private function runPool(array $tasks, callable $onSuccess, callable $onFailure): void
    {
        $this->poolQueue = $tasks;
        $this->poolDeferred = [];
        $this->poolInflight = [];
        $this->poolMeta = [];

        try {
            while (!$this->stopRequested) {
                $this->promoteDeferred();
                $this->fillPool($onFailure);

                if ($this->poolInflight === []) {
                    if ($this->poolQueue === [] && $this->poolDeferred === []) {
                        return;
                    }
                    $this->idle();
                    continue;
                }

                $this->pumpStream($onSuccess, $onFailure);
                $this->expireStalled($onFailure);
                $this->reportProgress();
            }
        } finally {
            $this->releaseAll();
        }
    }

    /**
     * Moves the tasks whose backoff has elapsed back into the queue.
     */
    private function promoteDeferred(): void
    {
        $now = microtime(true);
        $keep = [];

        foreach ($this->poolDeferred as $item) {
            if ($item['at'] <= $now) {
                $this->poolQueue[] = $item['task'];
                continue;
            }
            $keep[] = $item;
        }

        $this->poolDeferred = $keep;
    }

    /**
     * Starts requests until the pool is saturated, the queue runs dry or the
     * cooldown after a burst of errors kicks in.
     *
     * @param callable(ScrapeTask, bool, string): void $onFailure
     */
    private function fillPool(callable $onFailure): void
    {
        while (
            count($this->poolInflight) < $this->activeConcurrency
            && $this->poolQueue !== []
            && $this->coolDownUntil <= microtime(true)
        ) {
            $task = array_shift($this->poolQueue);
            $key = $task['key'] . '#' . $task['attempt'];

            try {
                $this->poolInflight[$key] = $this->httpClient->request(
                    'GET',
                    $task['url'],
                    $this->requestOptions(),
                );
            } catch (\Throwable $e) {
                $this->penalize();
                $this->failTask($task, false, $e->getMessage(), $onFailure);
                continue;
            }

            $this->poolMeta[$key] = ['task' => $task, 'startedAt' => microtime(true)];
        }
    }

    /**
     * Consumes one stream() pass over the in-flight responses. Returns as soon
     * as the pool has free slots and queued work so it can be refilled, or
     * when the pass goes idle.
     *
     * @param callable(ScrapeTask, string): list<ScrapeTask> $onSuccess
     * @param callable(ScrapeTask, bool, string): void $onFailure
     */
    private function pumpStream(callable $onSuccess, callable $onFailure): void
    {
        foreach ($this->httpClient->stream($this->poolInflight, self::STREAM_TICK) as $response => $chunk) {
            $error = null;
            $first = false;
            $last = false;

            try {
                if ($chunk->isTimeout()) {
                    return;
                }
                $first = $chunk->isFirst();
                $last = !$first && $chunk->isLast();
            } catch (TransportExceptionInterface $e) {
                $error = $e->getMessage();
            }

            if ($error === null && $first) {
                try {
                    // Claim the status code as soon as the headers arrive:
                    // otherwise stream() checks it for us right after yielding
                    // this chunk and raises the HTTP error from inside the
                    // generator, aborting the whole run on a single 502.
                    $response->getStatusCode();

                    continue;
                } catch (TransportExceptionInterface $e) {
                    $error = $e->getMessage();
                }
            }

            if ($error === null && !$last) {
                continue;
            }

            $key = $this->inflightKey($response);
            if ($key === null || !isset($this->poolMeta[$key])) {
                continue;
            }

            $task = $this->poolMeta[$key]['task'];
            unset($this->poolInflight[$key], $this->poolMeta[$key]);

            if ($error !== null) {
                $this->release($response);
                $this->penalize();
                $this->failTask($task, false, $error, $onFailure);

                return;
            }

            $this->handleCompleted($response, $task, $onSuccess, $onFailure);
            $this->release($response);

            if ($this->stopRequested) {
                return;
            }

            if ($this->poolQueue !== [] && count($this->poolInflight) < $this->activeConcurrency) {
                return;
            }
        }
    }

    /**
     * Turns a finished response into either follow-up tasks or a failure.
     *
     * @param ScrapeTask $task
     * @param callable(ScrapeTask, string): list<ScrapeTask> $onSuccess
     * @param callable(ScrapeTask, bool, string): void $onFailure
     */
    private function handleCompleted(
        ResponseInterface $response,
        array $task,
        callable $onSuccess,
        callable $onFailure,
    ): void {
        try {
            $status = $response->getStatusCode();
            $body = $status >= 200 && $status < 300 ? $response->getContent(false) : '';
        } catch (TransportExceptionInterface $e) {
            $this->penalize();
            $this->failTask($task, false, $e->getMessage(), $onFailure);

            return;
        }

        if (in_array($status, self::RETRYABLE_STATUS, true)) {
            $this->penalize();
            $this->failTask($task, false, 'HTTP ' . $status, $onFailure);

            return;
        }

        if ($status < 200 || $status >= 300) {
            $this->reward();
            $this->failTask($task, true, 'HTTP ' . $status, $onFailure);

            return;
        }

        $this->reward();

        try {
            foreach ($onSuccess($task, $this->toUtf8($body)) as $newTask) {
                $this->poolQueue[] = $newTask;
            }
        } catch (\Throwable $e) {
            $this->failTask($task, true, $e->getMessage(), $onFailure);
        }

        $this->reportProgress();
    }

    /**
     * Re-queues a failed task with an exponential backoff while it still has
     * attempts left, otherwise hands it over to the caller. Permanent failures
     * (a 4xx or an unreadable page) are never retried.
     *
     * @param ScrapeTask $task
     * @param callable(ScrapeTask, bool, string): void $onFailure
     */
    private function failTask(array $task, bool $permanent, string $reason, callable $onFailure): void
    {
        if (!$permanent && $task['attempt'] < self::MAX_RETRIES && !$this->stopRequested) {
            $task['attempt']++;
            $this->poolDeferred[] = [
                'at' => microtime(true) + $this->backoff($task['attempt']),
                'task' => $task,
            ];

            return;
        }

        $onFailure($task, $permanent, $reason);
        $this->reportProgress();
    }

    /**
     * Force-fails requests that produced no activity at all within
     * STALL_TIMEOUT, guarding against responses the transport never finishes.
     *
     * @param callable(ScrapeTask, bool, string): void $onFailure
     */
    private function expireStalled(callable $onFailure): void
    {
        $now = microtime(true);

        foreach ($this->poolMeta as $key => $item) {
            if ($now - $item['startedAt'] < self::STALL_TIMEOUT) {
                continue;
            }

            if (isset($this->poolInflight[$key])) {
                $this->release($this->poolInflight[$key]);
            }
            unset($this->poolInflight[$key], $this->poolMeta[$key]);

            $this->penalize();
            $this->failTask($item['task'], false, sprintf('нет ответа за %d с', (int) self::STALL_TIMEOUT), $onFailure);
        }
    }

    /**
     * Waits for the next deferred task or the end of the cooldown, capped so
     * the progress line and the stop flag keep being checked.
     */
    private function idle(): void
    {
        $now = microtime(true);
        $wake = [];

        if ($this->poolQueue !== [] && $this->coolDownUntil > $now) {
            $wake[] = $this->coolDownUntil;
        }
        foreach ($this->poolDeferred as $item) {
            $wake[] = $item['at'];
        }

        $this->reportProgress();

        $delay = $wake === [] ? 0.05 : min(1.0, max(0.05, min($wake) - $now));
        usleep((int) ($delay * 1_000_000));
    }

    private function backoff(int $attempt): float
    {
        return min(2 ** $attempt, 60) + (mt_rand(0, 1000) / 1000);
    }

    /**
     * Reacts to a retryable answer: a long enough burst halves the pool and
     * puts every worker on a short cooldown.
     */
    private function penalize(): void
    {
        $this->successStreak = 0;
        $this->errorStreak++;

        if ($this->errorStreak < self::ERROR_BURST) {
            return;
        }

        $this->errorStreak = 0;
        $this->activeConcurrency = max(self::MIN_CONCURRENCY, intdiv($this->activeConcurrency, 2));
        $this->coolDownUntil = microtime(true) + self::COOLDOWN;
    }

    /**
     * Reacts to a clean answer: a long enough streak grows the pool back by
     * one step towards the requested concurrency.
     */
    private function reward(): void
    {
        $this->errorStreak = 0;

        if (++$this->successStreak < self::RECOVER_AFTER) {
            return;
        }

        $this->successStreak = 0;
        $this->activeConcurrency = min($this->concurrency, $this->activeConcurrency + 1);
    }

    /**
     * Releases a response without letting it throw: an un-initialized response
     * checks its status code from the destructor and would otherwise raise the
     * HTTP error at an arbitrary point of the run.
     */
    private function release(ResponseInterface $response): void
    {
        try {
            $response->cancel();
        } catch (\Throwable) {
            // Already finished or already cancelled.
        }
    }

    private function releaseAll(): void
    {
        foreach ($this->poolInflight as $response) {
            $this->release($response);
        }

        $this->poolInflight = [];
        $this->poolMeta = [];
    }

    private function inflightKey(ResponseInterface $response): ?string
    {
        foreach ($this->poolInflight as $key => $candidate) {
            if ($candidate === $response) {
                return $key;
            }
        }

        return null;
    }

    // ------------------------------------------------------------ progress --

    private function reportProgress(bool $force = false): void
    {
        if ($this->progress === null) {
            return;
        }

        $now = microtime(true);
        if (!$force && $this->lastProgressAt !== null && ($now - $this->lastProgressAt) < 1.0) {
            return;
        }
        $this->lastProgressAt = $now;

        $done = $this->datesDone + $this->currentDateFraction();
        $pct = $this->datesTotal > 0 ? 100 * $done / $this->datesTotal : 0.0;

        $elapsed = $now - $this->startTime;
        $progressed = $done - $this->datesDoneAtStart;
        $eta = '--';
        if ($progressed > 0.05 && $done < $this->datesTotal) {
            $eta = $this->formatDuration(($elapsed / $progressed) * ($this->datesTotal - $done));
        }

        ($this->progress)(sprintf(
            '[%d/%d] %s · страницы %d/%d (%d) · авторы %d/%d (%d) · монстров %d · %.1f%% · прошло %s · ETA %s',
            min($this->dateIndex, $this->datesTotal),
            $this->datesTotal,
            $this->currentDate === '' ? '—' : $this->formatDate($this->currentDate),
            $this->datePagesDone,
            $this->datePagesTotal,
            $this->datePagesFailed,
            $this->dateAuthorsDone,
            $this->dateAuthorsTotal,
            $this->dateAuthorsFailed,
            count($this->newMonsters),
            $pct,
            $this->formatDuration($elapsed),
            $eta,
        ));
    }

    /**
     * Share of the date being processed, so the percentage moves smoothly
     * inside a date instead of jumping once per day. Both phases of a date
     * weigh the same half.
     */
    private function currentDateFraction(): float
    {
        if ($this->currentDate === '' || $this->dateCounted) {
            return 0.0;
        }

        $pages = $this->datePagesTotal > 0 ? min(1.0, $this->datePagesDone / $this->datePagesTotal) : 0.0;

        // A date whose authors were all cached has nothing left to do once its
        // pages are read, so its second half must not stay at zero.
        $authors = match (true) {
            $this->datePhase < 1 => 0.0,
            $this->dateAuthorsTotal > 0 => min(1.0, $this->dateAuthorsDone / $this->dateAuthorsTotal),
            default => 1.0,
        };

        return 0.5 * $pages + 0.5 * $authors;
    }

    private function formatDate(string $date): string
    {
        [$year, $month, $day] = explode('-', $date);

        return $day . '.' . $month . '.' . $year;
    }

    private function formatDuration(float $seconds): string
    {
        $seconds = max(0, (int) round($seconds));
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%dч %02dм', $hours, $minutes);
        }

        return sprintf('%dм %02dс', $minutes, $seconds % 60);
    }

    // --------------------------------------------------------------- state --

    /**
     * @return array<string, true>
     */
    private function existingMonsterLogins(): array
    {
        $logins = [];
        foreach ($this->monsterRepository->findOrderedByPoems() as $monster) {
            $logins[$monster->getLogin()] = true;
        }

        return $logins;
    }

    private function rememberAuthor(string $login, string $author, ?int $poems): void
    {
        $this->scanBuffer[] = [
            'login' => $login,
            'author' => $author,
            'poems' => $poems,
            'checkedAt' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ];

        // Authors are keyed by login and stay valid on their own, so a long
        // date does not have to risk its whole buffer on an abrupt kill.
        if (count($this->scanBuffer) >= self::SCAN_STATE_BATCH) {
            $this->flushScanState();
        }
    }

    private function flushScanState(): void
    {
        if ($this->scanBuffer === [] || $this->dryRun) {
            $this->scanBuffer = [];

            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO scan_state (login, author, poems, checked_at) VALUES (:login, :author, :poems, :checkedAt) '
            . 'ON CONFLICT(login) DO UPDATE SET author = excluded.author, '
            . 'poems = excluded.poems, checked_at = excluded.checked_at',
        );

        $this->db->beginTransaction();
        try {
            foreach ($this->scanBuffer as $row) {
                $stmt->bindValue(':login', $row['login']);
                $stmt->bindValue(':author', $row['author']);
                $stmt->bindValue(':poems', $row['poems'], $row['poems'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
                $stmt->bindValue(':checkedAt', $row['checkedAt']);
                $stmt->execute();
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->scanBuffer = [];
    }

    /**
     * Folds the write-ahead log back into scan.db.
     *
     * The connection stays open for the whole multi-hour run, so without this
     * everything committed — including the schema — would sit in scan.db-wal
     * until SQLite's own threshold is reached, and anyone opening scan.db on
     * its own (a copy, a read-only tool, a viewer that cannot lock -shm) would
     * see an empty database.
     */
    private function checkpoint(): void
    {
        if ($this->dryRun) {
            return;
        }

        try {
            $stmt = $this->db->query('PRAGMA wal_checkpoint(TRUNCATE)');
            if ($stmt !== false) {
                $stmt->closeCursor();
            }
        } catch (\PDOException) {
            // A busy checkpoint is not worth failing the run over; the next
            // date tries again.
        }
    }

    private function isStreamDateDone(string $date): bool
    {
        $stmt = $this->db->prepare('SELECT done FROM stream_dates WHERE date = :date');
        $stmt->execute(['date' => $date]);
        $done = $stmt->fetchColumn();

        return $done === '1' || $done === 1;
    }

    private function markDateDone(string $date): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stream_dates (date, done) VALUES (:date, 1) ON CONFLICT(date) DO UPDATE SET done = 1',
        );
        $stmt->execute(['date' => $date]);
    }

    /**
     * @param list<string> $dates
     * @return list<string>
     */
    private function pendingDates(array $dates): array
    {
        if ($this->dryRun) {
            return [];
        }

        $pending = [];
        foreach ($dates as $date) {
            if (!$this->isStreamDateDone($date)) {
                $pending[] = $date;
            }
        }

        return $pending;
    }

    /**
     * @return list<string>
     */
    private function dateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $dates = [];
        $current = $from;
        while ($current <= $to) {
            $dates[] = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
        }

        return $dates;
    }

    // --------------------------------------------------------------- pages --

    private function streamUrl(string $date, int $start): string
    {
        [$year, $month, $day] = explode('-', $date);
        $query = sprintf('day=%02d&month=%02d&year=%04d&topic=all', (int) $day, (int) $month, (int) $year);

        if ($start > 0) {
            $query .= '&start=' . $start;
        }

        return self::BASE_URL . '/poems/list.html?' . $query;
    }

    private function parseDayMaxPoemNumber(string $html, string $date): int
    {
        [$year, $month, $day] = explode('-', $date);
        if (!preg_match_all('~href="/' . $year . '/' . $month . '/' . $day . '/(\d+)"~', $html, $matches)) {
            return 0;
        }

        $max = 0;
        foreach ($matches[1] as $number) {
            $value = (int) $number;
            if ($value > $max) {
                $max = $value;
            }
        }

        return $max;
    }

    /**
     * @param array<string, string> $collected
     */
    private function collectStreamAuthors(string $html, array &$collected): void
    {
        preg_match_all(
            '~<a href="/avtor/([^"]+)" class="authorlink">([^<]+)</a>~',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $collected[$match[1]] = $match[2];
        }
    }

    private function toUtf8(string $body): string
    {
        return mb_convert_encoding($body, 'UTF-8', 'Windows-1251');
    }

    /**
     * @return array<string, mixed>
     */
    private function requestOptions(): array
    {
        return [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept-Language' => 'ru-RU,ru;q=0.9',
            ],
            'timeout' => 20,
            'max_duration' => 60,
        ];
    }

    private function openStateDb(bool $reset): \PDO
    {
        $dir = $this->projectDir . '/var/monster_scan';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file = $dir . '/scan.db';
        if ($reset && !$this->dryRun) {
            foreach ([$file, $file . '-wal', $file . '-shm'] as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }

        $pdo = new \PDO('sqlite:' . $file);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS scan_state ('
            . 'login TEXT PRIMARY KEY, '
            . 'author TEXT NOT NULL DEFAULT "", '
            . 'poems INTEGER, '
            . 'checked_at TEXT NOT NULL'
            . ')',
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS scan_state_checked_at ON scan_state (checked_at)');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS stream_dates ('
            . 'date TEXT PRIMARY KEY, '
            . 'done INTEGER NOT NULL DEFAULT 0'
            . ')',
        );

        return $pdo;
    }
}
