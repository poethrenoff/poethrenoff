# AGENTS.md — инструкции агента для проекта "PoetHrenoff"

> Единый источник контекста по проекту. Агент подгружает его в начале каждой сессии.
> В конце сессии дополняй его новыми знаниями (см. «Цикл контекста»).

## Общие правила

- Язык общения с пользователем: **русский**.

## Цикл контекста

- **В начале каждой сессии** прочитай этот файл и используй накопленные
  знания (структура, паттерны, сделанное ранее, открытые задачи, команды).
- **В конце каждой сессии**, где получены новые сведения (новый код, изменения
  архитектуры, договорённости, выявленные риски, команды), **дополни этот файл**
  этими данными. Не удаляй ранее записанное без причины; обновляй статус
  пунктов (сделано / не сделано).
- **[`CHANGELOG.md`](CHANGELOG.md)** — поддерживай его актуальным **постоянно,
  в фоновом режиме**. При каждом изменении кода **сразу (в той же правке/
  сессии)** добавляй запись в секцию текущей даты. Формат — маркированный список
  (`- ...`), описывающий **что** изменено и **почему** (кратко). Записи в
  `CHANGELOG.md` — обязательный завершающий шаг каждой логической единицы.

## Архитектура Multi-Domain

Проект использует единое Symfony 8.1 ядро для трёх сайтов. Точки входа в `htdocs/`:
- `htdocs/www/` — `poethrenoff.ru`
- `htdocs/blog/` — `lo.blog.poethrenoff.ru`
- `htdocs/work/` — `lo.work.poethrenoff.ru`

### Особенности реализации
1. **Context-aware Kernel:** В каждом `index.php` устанавливается
   `$_SERVER['APP_SITE_CONTEXT']`.
2. **Изоляция кэша:** `Kernel.php` переопределяет `getCacheDir()` и
   `getLogDir()`, добавляя контекст в путь (например, `var/cache/dev/www`).
   Это позволяет иметь разные параметры контейнера для каждого сайта.
3. **Параметры:** Контекст доступен в контейнере через параметр `app.site_context`.
4. **Ассеты и загрузки:** В каждой поддиректории `htdocs/` созданы симлинки
   `bundles` и `assets` на папки в `public/`. Загрузки хранятся в
   `htdocs/{context}/upload/`, что позволяет обращаться к медиафайлам
   по унифицированному пути `/upload/...` независимо от сайта.
5. **Локальная разработка:** Для доступа по локальным доменам добавьте их
   в `/etc/hosts`. **ВАЖНО:** для работы `getUserMedia` браузеры требуют
   **Secure Context**. На локальных доменах без HTTPS API
   `navigator.mediaDevices` будет `undefined`. Для разработки рекомендуется
   использовать `localhost` или настроить локальный HTTPS.
6. **Роутинг:** Все контроллеры — в общей папке `src/Controller/`. Маршруты
   настраиваются в `config/routes.yaml` — каждый контроллер импортируется
   с `condition: "request.server.get('APP_SITE_CONTEXT') == '...'"` для привязки
   к контексту. SecurityController (login/logout) импортируется без условия —
   доступен на всех сайтах. Бандловые маршруты подгружаются из `config/routes/`
   автоматически через `MicroKernelTrait`.
7. **Версионирование ассетов:** Статические ассеты версионируются по `mtime`
   через `App\Asset\FileVersionStrategy`. К URL CSS/JS добавляется
   query-параметр `?v=<timestamp>`, предотвращая устаревшее кеширование.

## Сервисный слой

Проект использует вынесенную бизнес-логику в сервисы (`src/Service/`):
- `FileUploadService` — загрузка, замена и удаление аудиофайлов
- `SearchService` — поиск работ и блог-записей
- `CommentService` — санитизация комментариев, автолинкинг, построение деревьев,
  валидация автора
- `WorkService` — версионирование стихов, переупорядочивание, парсинг дат,
  trash/restore/delete
- `MonsterService` — рейтинг авторов Стихи.ру (таблица `Monster`, «Монстры»):
  `fetchAuthorData()` загружает страницу `https://stihi.ru/avtor/{login}`
  (Windows-1251 → UTF-8) и парсит количество произведений, имя автора и дату
  последней активности; `parseAuthorHtml()` — чистый парсинг HTML (общий для
  команд update/discover); `recalculatePlaces()` пересчитывает `place`/`place_old`
  по убыванию количества произведений. Обновление запускается командой
  `app:monster:update` вручную (без cron). Sonata-админ `MonsterAdmin` —
  только список (группа «Рейтинг Стихи.ру»), с дельтами мест/количества,
  выделением ника `poethrenoff` и зачёркиванием неактивных авторов. Начальный
  список (582 автора, все ≥5000 произведений) — из дампа старого сайта через
  `app:migrate:legacy`.
- `MonsterDiscoveryService` — поиск новых «монстров» (авторов с ≥5000
  произведений) по дневному потоку `stihi.ru/poems/list.html`. Запуск —
  командой `app:monster:discover` (вручную, без cron). Заменил неудачные
  legacy-скрипты `var/authors.py` / `var/authors_update.py`.
  - **Алгоритм — конвейер по дням.** Цикл по датам наверху: для каждой даты
    читается первая страница потока (из неё известно число страниц дня),
    затем остальные страницы — так собираются уникальные авторы дня; затем
    проверяются счётчики тех из них, кого ещё нет в `Monster` и кто не
    проверялся в пределах TTL; затем данные дня коммитятся, и только после
    этого дата помечается обработанной. **День — единица прогресса и
    возобновления**: прерывание теряет максимум текущий день.
  - **Состояние** — локальная SQLite `var/monster_scan/scan.db`:
    `stream_dates` (обработанные даты) и `scan_state` (логин, имя, число
    произведений, `checked_at`). Записи авторов копятся весь день и пишутся
    одной транзакцией в конце дня, поэтому повторные встречи автора в
    последующих днях не стоят HTTP-запросов. TTL повторной проверки —
    `--recheck-days` (по умолчанию 90 дней). После каждого закрытого дня
    делается `PRAGMA wal_checkpoint(TRUNCATE)`: соединение открыто весь
    многочасовой прогон, и без чекпойнта всё закоммиченное остаётся в
    `scan.db-wal`, а открытый отдельно `scan.db` выглядит пустым.
    **Смотреть состояние во время прогона — только изнутри контейнера**
    (файлы принадлежат `root`, а для чтения WAL-базы SQLite нужно право
    записи рядом с `-shm`):
    `docker compose exec php php -r '$p=new PDO("sqlite:/var/www/symfony/var/monster_scan/scan.db"); echo $p->query("SELECT COUNT(*) FROM scan_state")->fetchColumn();'`
  - **Незавершённый день** (исчерпаны ретраи на 5xx/таймауте) не помечается
    обработанным и будет перечитан при следующем запуске, но данные авторов,
    полученные в этот день, всё равно сохраняются. Постоянные отказы (4xx,
    нечитаемая страница автора) не блокируют день: логин пишется в
    `scan_state` с `poems = NULL`, чтобы не дёргать его до истечения TTL.
  - **HTTP** — ограниченный пул на базе `HttpClientInterface::stream()`
    (curl multi, один процесс). Ретраи с экспоненциальной паузой и джиттером
    на 408/429/500/502/503/504 ставятся в отложенную очередь (пул при этом
    не простаивает); серия ошибок вдвое ужимает параллельность и включает
    короткий общий cooldown, серия успехов возвращает её обратно.
    **Критично**: `stream()` сам вызывает `getHeaders(true)` сразу после
    первого чанка, а деструктор неинициализированного ответа — свою проверку
    статуса, поэтому на первом чанке обязательно вызывается
    `getStatusCode()`, а любой отбрасываемый ответ — `cancel()`. Без этого
    одна 502 роняла всю команду из произвольного места.
  - **Прерывание** — команда реализует `SignalableCommandInterface`: первый
    SIGINT/SIGTERM просит сервис остановиться (накопленное коммитится),
    второй завершает процесс немедленно.
  - Полный диапазон (~265 дат) занимает часы — запускать в фоне
    (`nohup` / `screen` / `tmux`).
- `RecognizeService` — state machine для асинхронного распознавания речи:
  создание задач, пошаговое выполнение через poll, обработка ошибок
- `YandexService` — распознавание речи (асинхронное через STT v3)
  и постобработка текста с помощью YandexGPT (модель `yandexgpt-lite`) для
  расстановки знаков препинания и формирования структуры стихотворения.
  Требует роли `ai.speechkit-stt.user`, `ai.languageModels.user` и доступа
  к S3 (`storage.uploader`, `storage.viewer`).
- `TelegramService` — общение с Telegram **только** через AWS-мост, без прямого
  доступа: `computeReplies(array $update): list<string>` (вычисление ответов
  бота на сыром массиве апдейта — `/start`, `/help`, `/random`, поиск
  по избранному), `formatWork(Work|Poem)`,
  `publish(int|string $chatId, string $text): ?int` (событийный push
  «сайт → AWS → Telegram»; возвращает реальный `message_id` из `sendMessage`
  или `null` при ошибке). SDK `irazasyed/telegram-bot-sdk` удалён — сайт
  с Telegram напрямую не общается.
- **Схема «демон ↔ шлюз»:** на проде хостинг блокирует и исходящие к Telegram,
  и входящие webhook — Telegram в России считается навсегда недоступным,
  общение только через AWS-мост. Постоянный Python-демон
  `telegram-bridge/telegram-bridge.py` на AWS EC2 (ставится в `/usr/local/bin/`,
  systemd-юнит `telegram-bridge/telegram-bridge.service`, подробная установка —
  `telegram-bridge/telegram-bridge.md`) получает обновления long-polling
  `getUpdates` и форвардит их на сайт: `POST /bot` (контекст `www`)
  с заголовком `X-Bot-Secret` против `TELEGRAM_BOT_SECRET`, в ответ —
  `{"replies": [...]}`; демон сам отправляет каждый ответ в Telegram. Секрет
  задаётся через `#[Autowire(env: 'TELEGRAM_BOT_SECRET')]` в `BotController`.
  Webhook на стороне Telegram должен быть удалён.
- Push «сайт → AWS → Telegram»: `TelegramService::publish()` POST'ит
  на `TELEGRAM_BRIDGE_URL` с заголовком `X-Bot-Secret`. Слушатель на демоне —
  `POST /push` (`PUSH_HOST`/`PUSH_PORT`, по умолчанию `0.0.0.0:8080`),
  проверяет тот же секрет и шлёт `sendMessage(chat_id, text, HTML)`, отвечая
  `{"ok": true, "message_id": <id>}`. `chat_id` универсален (канал `@username`/id
  или ЛС). Ручная отправка — консольной командой `app:telegram:send`.

Контроллеры делегируют бизнес-логику сервисам и отвечают только за HTTP-обработку.

Для устранения дублирования между доменами выделены общие абстракции:
- `App\Trait\HasDefaultTitleTrait` — общие методы для `Poem` и `Work`.
- `App\Trait\CommentFieldsTrait` — общие поля и методы для `BlogComment`
  и `WorkComment`.

### Безопасность и аутентификация

- **Session + remember-me кросс-доменно:** `cookie_domain:
  '.%env(BASE_DOMAIN)%'` (`poethrenoff.ru`) на сессию и remember-me cookie
  позволяет пользователю быть залогиненным на всех поддоменах.
- **Важно для контроллеров с `IsGranted`:** когда сессия истекает,
  `RememberMeAuthenticator` пересоздаёт токен как `RememberMeToken`.
  `RememberMeToken` проходит `IS_AUTHENTICATED_REMEMBERED`, но **не**
  `IS_AUTHENTICATED_FULLY`. `ExceptionListener` при `IS_AUTHENTICATED_FULLY`
  redirectит на `/admin/login` вместо 403. Используй `IS_AUTHENTICATED_REMEMBERED`
  (или только `ROLE_*`) на контроллерах, где remember-me должен работать.
- `WorkController` и `AudioController` используют
  `#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]` — не `IS_AUTHENTICATED_FULLY`.

## Команды

PHP и инструменты запускаются **только через Docker**. Не пытайся запускать
`bin/phpcs`, `bin/phpstan` или `bin/console` напрямую — PHP должен быть доступен
в PATH, что выполняется только внутри контейнера.

```bash
make build                    # Сборка образов Docker
make up                       # Запуск контейнеров
make stop                     # Остановка
make down                     # Остановка и удаление контейнеров
make update                   # composer update -W внутри php-контейнера

make phpcs                    # Проверка стиля кода (PHP_CodeSniffer)
make phpcbf                   # Автоисправление стиля кода
make analyze                  # Статический анализ (PHPStan level 8)
make cache-clear              # Очистка кэша (всех контекстов)
make makemigrations           # Создание миграций Doctrine
make migrate                  # Выполнение миграций
make loaddata                 # Загрузка фикстур
make backup <dir>             # Бэкап БД
make restore <dir>            # Восстановление БД из последнего бэкапа
```

Альтернатива `make` — прямой вызов:
```bash
docker compose exec php bin/console cache:clear
docker compose exec php bin/console debug:router
docker compose exec php bin/console app:create-admin <email> <password>
docker compose exec php bin/console app:migrate:legacy
docker compose exec php bin/console app:telegram:send <chatId> <text>
docker compose exec php bin/console app:transfer:poems   # перенос стихов из Мастерской на сайт (интерактивно)
docker compose exec php bin/console app:stihiru:publish  # публикация последнего сборника на stihi.ru (--dry-run для проверки)
docker compose exec php bin/console app:monster:update   # обновление рейтинга авторов Стихи.ру (--login/--limit/--dry-run)
docker compose exec php bin/console app:monster:discover # поиск новых «монстров» по потоку stihi.ru (--from/--to/--concurrency/--recheck-days/--dry-run/--reset)
docker compose exec php bin/console app:export:corpus    # экспорт корпуса в JSONL (--output, по умолчанию var/export/corpus)
docker compose exec php bin/phpstan analyse --no-progress
```

## Конвенции для PHPStan (level 8)

- Sonata-админы: класс обязательно с `/** @extends AbstractAdmin<Entity> */`
  и `use App\Entity\X;`; `getSubject()`/`prePersist($object)` уже типизированы
  как сущность — дополнительные `instanceof`/тернарники PHPStan флагует
  как `alwaysTrue`.
- Сущности: коллекции Doctrine — `@var Collection<int, Entity>` на свойстве
  и `@return Collection<int, Entity>` на геттере; у сущности должен быть
  `setId()` (иначе `property.unusedType` для `?int $id`).
- Репозитории: возвраты массивов — `list<Entity>` (или shape
  `array{prev: X|null, next: X|null}`); `@method findBy/findOneBy` — только одной
  строкой (многострочный `@method` PHPStan не парсит) и с типами значений
  (`mixed[]`), иначе phpcs ругается на длину строки.
- Пагинация Knp: `getPaginationData()` есть только у `SlidingPagination`,
  не входит в `PaginationInterface`. Используй
  `SearchService::buildPagination(PaginationInterface)`; репозитории возвращают
  `PaginationInterface<int, Entity>` (с `/** @var PaginationInterface<int,
  Entity> $pagination */` перед `paginate()`).

## Git-коммиты

Текст коммитов пишется на **английском языке** по стандарту **Conventional Commits**.
**ВАЖНО: Никогда не выполнять `git commit` самостоятельно без явного указания
пользователя.**

### Формат

```
<type>(<scope>): <short description>

[optional body]

[optional footer]
```

### Типы

| Тип        | Когда использовать                                         |
|------------|--------------------------------------------------------------|
| `feat`     | Новая функциональность                                        |
| `fix`      | Исправление бага                                              |
| `docs`     | Изменения только в документации                               |
| `style`    | Форматирование, пробелы и т.п. — без изменения логики          |
| `refactor` | Рефакторинг без изменения поведения                           |
| `perf`     | Изменения, улучшающие производительность                       |
| `test`     | Добавление или исправление тестов                              |
| `build`    | Изменения в системе сборки, зависимостях                        |
| `ci`       | Изменения в CI-конфигурации                                     |
| `chore`    | Рутинные задачи, не влияющие на продакшн-код                    |
| `revert`   | Откат предыдущего коммита                                       |

### Правила

- Заголовок: не длиннее 50–72 символов.
- Повелительное наклонение: `add`, `fix`, `remove`, а не `added`, `fixed`,
  `removes`.
- Без точки в конце заголовка.
- `scope` (область) необязателен, но желателен, если изменение локализовано:
  `feat(auth): ...`.
- Тело коммита (если есть) объясняет **что** и **почему**, а не **как**,
  отделяется от заголовка пустой строкой.
- Breaking changes: помечаются `!` после типа/области и/или футером
  `BREAKING CHANGE:`.
- Один коммит = одно логическое изменение (атомарность).

### Порядок действий агента по команде «Закоммить» / «Commit»

Когда пользователь просит закоммитить изменения, агент должен:

1. Выполнить `git status` и `git diff` (staged и unstaged), чтобы увидеть все
   изменения.
2. Если есть несколько несвязанных изменений — разбить их на отдельные
   атомарные коммиты, не смешивать разное в одном коммите.
3. Для каждого логического изменения определить правильный `type` (и `scope`,
   если применимо) по таблице выше.
4. Составить сообщение коммита на английском языке, в повелительном наклонении,
   по формату выше.
5. Добавлять в staging только релевантные для конкретного коммита файлы
   (`git add <files>`), затем выполнять `git commit -m "..."`.
6. Если изменение ломающее (breaking), добавить `!` после типа/области и футер
   `BREAKING CHANGE:` с описанием последствий.
7. Не выполнять `git push` автоматически, если это не было явно указано.
8. После коммита показать пользователю краткую сводку по созданным коммитам
   (хэш + сообщение).

### Примеры

```
feat(auth): add OAuth2 login via Google

fix(cart): correct total price calculation on discount

docs(readme): update installation instructions

refactor(api): extract validation logic into separate module

feat(payments)!: switch to new billing provider

BREAKING CHANGE: old payment API endpoints are removed
```

## История изменений

Подробная история изменений вынесена в отдельный файл **[`CHANGELOG.md`](CHANGELOG.md)**.
При необходимости сверяйся с ним для понимания контекста прошлых решений.

## Telegram-бот (схема «демон ↔ шлюз»)

- Бот обрабатывает `POST /bot` (контекст `www`), команды: `/start`/`/help`
  (описание), `/random` (случайный стих из избранного), любой другой текст —
  поиск по стихам из избранного и выдача случайного из найденных. Ответы
  вычисляет `TelegramService::computeReplies()` на сыром массиве апдейта
  и возвращает `{"replies": [...]}` демону; сам сайт в Telegram ничего не шлёт.
- Переменные сайта: `TELEGRAM_BOT_SECRET` (общий секрет шлюза),
  `TELEGRAM_BRIDGE_URL` (адрес AWS `POST /push`). Токен бота `TELEGRAM_API_TOKEN`
  на сайте **не нужен** — он живёт только в `/etc/telegram-bridge.env` на AWS.
- **Установка демона на AWS EC2:** см. подробную инструкцию
  `telegram-bridge/telegram-bridge.md`. Коротко: скопировать
  `telegram-bridge/telegram-bridge.py` в `/usr/local/bin/telegram-bridge.py`,
  создать `/etc/telegram-bridge.env` (`TELEGRAM_API_TOKEN`,
  `SITE_URL=https://poethrenoff.ru/bot`, `BOT_SECRET` — совпадает
  с `TELEGRAM_BOT_SECRET` на сайте; при необходимости `PUSH_HOST`/`PUSH_PORT`),
  положить юнит `telegram-bridge/telegram-bridge.service` в
  `/etc/systemd/system/`, затем `systemctl enable --now telegram-bridge`.
  Требуется пакет `requests`. Для приёма пуша с сайта открыть порт слушателя
  (по умолчанию `8080`) в security group EC2 и firewall ОС.
- **Тестирование шлюза с сайта:** `POST /bot` с заголовком `X-Bot-Secret`
  и телом-апдейтом, в ответ — `{"replies": [...]}`. Ручной push — командой
  `app:telegram:send <chatId> <text>` (через `TelegramService::publish()`).
  Локальной команды прослушивания больше нет (удалена вместе с SDK).

### Локальное тестирование (long-polling)

Демон/шлюз на проде работает через long-polling.

- **Webhook и long-polling несовместимы** — активный webhook блокирует
  `getUpdates` (ошибка 409). На проде webhook должен быть удалён (демон работает
  через `getUpdates`). Если webhook уже настроен на боте, снимите его
  `deleteWebhook` с токена на AWS.

## Публикация на stihi.ru (`app:stihiru:publish`)

- Команда публикует последний сборник сайта на stihi.ru. Заменила legacy-скрипт
  `var/public.php`, где сборник/смещение были захардкожены; теперь всё
  вычисляется на лету.
- **Логика отбора:** публикуется `WorkGroup` с `is_favorite = true`
  и минимальной `position` (`WorkGroupRepository::findFavoriteActiveSorted()`).
  Произведения берутся из раздела по `position` **DESC** (обратный порядок),
  смещение на `offset` уже опубликованных на сайте произведений, за раз — не
  более `--limit` (по умолчанию 20, лимит сайта на сутки).
- **Сервис `StihiRuService`** (`src/Service/`), HTTP через Symfony HttpClient
  с ручным хранением cookies:
  - `login()` — `POST /cgi-bin/login/intro.pl`, из `Set-Cookie` извлекает
    `login=`/`pcode=`. Логин/пароль — `STIHIRU_LOGIN`/`STIHIRU_PASSWORD` из `.env`
    (`#[Autowire(env: ...)]`).
  - `findDestinationCollection()` — парсит `?list`: верхний сборник
    (`book=NN`) + число опубликованных в нём произведений (смещение).
  - `publishWork(Work, int $bookId)` — `code` со страницы `?add`, POST на
    `/cgi-bin/login/page.pl` (`title`, `text`, `code`, `block=save`,
    `text_topic=03`, `dogovor=on`), из ответа достаёт `link=YYYY/MM/DD/NNNN`
    и переносит в сборник через `/login/page.html?put&link=...&to={book}`;
    возвращает публичный URL `https://stihi.ru/YYYY/MM/DD/NNNN`.
  - **Кодировка:** сайт Windows-1251. Текст/заголовок/комментарий
    конвертируются `iconv('UTF-8', 'Windows-1251', ...)`; при неконвертируемых
    символах бросается `\RuntimeException` с перечнем символов.
  - Форматирование текста наследует legacy: удвоение ведущих/внутренних пробелов
    (`/^ +| {2,}/m`), комментарий добавляется через `\r\n\r\n`.
- **Проверка без публикации:** `--dry-run` логинится, получает сборник и выводит
  план, ничего не постит.

## Экспорт корпуса для ИИ-чатов (`app:export:corpus`)

- Контекст: проект «100000» (цель — 100 000 стихов), ведётся в чатах claude.ai
  с общей памятью. Решение Штаба (2026-09-26): источник истины для ИИ — БД
  сайта, не парсинг HTML и не txt-архив. Шаг 1 — экспорт в JSONL, шаг 2 —
  удалённый MCP-сервер только на чтение (проектируется, кода нет).
- Команда только читает БД через `EntityManager` и сущности (`toIterable()`
  + `clear()` батчами по 500, to-one связи fetch-join'ятся). Пишет в `--output`
  (по умолчанию `var/export/corpus`, каталог создаётся; `var/` в .gitignore).
  Контейнер php видит только папку проекта, поэтому в `~/Документы/100000/data/`
  файлы копирует Дмитрий сам.
- Файлы: `groups.jsonl`, `works.jsonl`, `poems.jsonl` (включая корзину),
  `publications.jsonl`, `blog_posts.jsonl` (с тегами), `blog_comments.jsonl`
  (без `info`), `manifest.json`. В `works.jsonl` поле `date` — ISO из строки
  `work.comment` (`dd.mm.yyyy` через `WorkService::parseCommentDate()`),
  `date_raw` — исходная строка; в `poems.jsonl` `date` — из `poem.comment`
  типа DATE. Нераспознанная дата — `date: null`, запись не входит в счёт
  дней; порога по числу строк нет — всё в базе считается законченным
  произведением. Счётчики для manifest копятся в самой команде.
- Локальная БД — копия продовой: дамп с хостинга накатывается `make restore`
  (`bin/restore <dir>`). Перед экспортом для Штаба — свежий дамп, иначе
  manifest устаревший.

## Операционные заметки

- **Нет тестов:** директория `tests/` пуста (`.gitignore`). Тестового runner'а
  и suite'ов нет.
- **Фронтенд:** в `public/assets/js/work.js` используется Alpine.js (x-show,
  x-model, x-for). Избегай синтаксиса, конфликтующего с Alpine (например,
  не называй методы `export`).
- **Данные:** фикстуры загружаются через `make loaddata`
  (doctrine:fixtures:load). Для миграции legacy-данных есть
  `bin/console app:migrate:legacy`.
- **Бэкап/восстановление:** скрипты `bin/backup` и `bin/restore` работают
  с Docker-сервисом MySQL по умолчанию.