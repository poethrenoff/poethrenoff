# Установка и настройка Telegram bridge на AWS EC2

> Пошаговая инструкция по развёртыванию демона `telegram-bridge.py` на AWS EC2 (Ubuntu).
> Схема «демон ↔ шлюз»: демон получает обновления от Telegram (long-polling `getUpdates`),
> форвардит их на сайт (`POST /bot`), отправляет ответы обратно в Telegram, а также
> принимает событийный push с сайта (`POST /push`) для публикации по инициативе сервера.

## Предварительные требования

- Ubuntu-инстанс AWS EC2 (например, `t3.micro`) с доступом и к Telegram, и к `poethrenoff.ru`.
- Вход на инстанс с правами `sudo`.
- Доступен исходник скрипта и юнита из проекта: `telegram-bridge.py`, `telegram-bridge.service`.
- Токен бота `TELEGRAM_API_TOKEN` (из BotFather) и общий секрет `BOT_SECRET`,
  совпадающий с `TELEGRAM_BOT_SECRET` на сайте.

---

## 1. Скопировать скрипт

Скопируйте `telegram-bridge.py` из проекта на инстанс, затем:

```bash
sudo cp /path/to/telegram-bridge.py /usr/local/bin/telegram-bridge.py
sudo chmod +x /usr/local/bin/telegram-bridge.py
```

---

## 2. Создать пользователя

Демон запускается от отдельного непривилегированного пользователя `telegram-bridge`:

```bash
sudo useradd --system --no-create-home --shell /usr/sbin/nologin telegram-bridge
```

---

## 3. Создать env-файл

```bash
sudo mkdir -p /etc/telegram-bridge.env.d
sudo touch /etc/telegram-bridge.env
```

Заполните содержимое (создайте и отредактируйте):

```bash
sudo nano /etc/telegram-bridge.env
```

```
TELEGRAM_API_TOKEN=<токен из BotFather>
SITE_URL=https://poethrenoff.ru/bot
BOT_SECRET=<общий секрет, совпадает с TELEGRAM_BOT_SECRET на сайте>
PUSH_HOST=0.0.0.0
PUSH_PORT=8080
```

Параметры:
- `TELEGRAM_API_TOKEN` — токен бота (обязателен).
- `SITE_URL` — эндпоинт шлюза на сайте (по умолчанию `https://poethrenoff.ru/bot`).
- `BOT_SECRET` — общий секрет для `X-Bot-Secret` (обязателен).
- `PUSH_HOST`/`PUSH_PORT` — необязательны; адрес, на котором слушает `POST /push`
  (по умолчанию `0.0.0.0:8080`).

Дайте пользователю права на чтение файла:

```bash
sudo chown telegram-bridge:telegram-bridge /etc/telegram-bridge.env
sudo chmod 600 /etc/telegram-bridge.env
```

Проверка чтения:

```bash
sudo -u telegram-bridge cat /etc/telegram-bridge.env
```

> Если не хотите давать демону доступ к секретам от отдельного пользователя —
> можно запускать от `root` и оставить файл `600 root` (уберите `User=` из юнита).

---

## 4. Установить systemd-юнит

```bash
sudo cp /path/to/telegram-bridge.service /etc/systemd/system/telegram-bridge.service
sudo chown root:root /etc/systemd/system/telegram-bridge.service
sudo chmod 644 /etc/systemd/system/telegram-bridge.service
```

Содержимое юнита (если копировали из проекта, уже готово):

```
[Unit]
Description=Telegram bridge daemon for PoetHrenoff
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
EnvironmentFile=/etc/telegram-bridge.env
ExecStart=/usr/local/bin/telegram-bridge.py
Restart=always
RestartSec=5
User=telegram-bridge

[Install]
WantedBy=multi-user.target
```

---

## 5. Установить зависимости

Демону нужен Python 3 и библиотека `requests`:

```bash
# Debian/Ubuntu (рекомендуется системный пакет)
sudo apt update
sudo apt install -y python3-requests

# или через pip для текущего интерпретатора
# sudo pip3 install requests
```

Проверка:

```bash
python3 -c "import requests; print(requests.__version__)"
```

---

## 6. Открыть порт для push-слушателя

Демон слушает `POST /push` (по умолчанию порт `8080`) для приёма публикаций с сайта.
Нужно открыть порт и в security group EC2, и в firewall ОС.

### В консоли AWS (security group)

1. EC2 → Instances → выбрать инстанс → вкладка **Security** → кликнуть на security group.
2. **Edit inbound rules** → **Add rule**:
   - Type: **Custom TCP**
   - Port range: `8080`
   - Source: IP сайта `poethrenoff.ru` (или `0.0.0.0/0`, если сайт не за IP-витлистом)
3. **Save rules**.

> Безопаснее ограничить Source конкретным IP сайта. Если источник неизвестен — временно
> `0.0.0.0/0`, но обязательно защитить эндпоинт секретом `X-Bot-Secret` (он уже проверяется).

### В firewall ОС (ufw)

```bash
sudo ufw allow 8080/tcp
sudo ufw status
```

Если ufw выключен, достаточно правил security group.

---

## 7. Запустить сервис

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now telegram-bridge
```

---

## 8. Проверка работоспособности

### Статус сервиса

```bash
sudo systemctl status telegram-bridge
```

Ожидается `Active: active (running)`.

### Просмотр логов

```bash
sudo journalctl -u telegram-bridge -f
```

При старте должно появиться:
```
Telegram bridge started (site: https://poethrenoff.ru/bot)
Push listener started on 0.0.0.0:8080
```

### Слушающий порт

```bash
sudo ss -tlnp | grep 8080
```

Ожидается строка с `:8080` и процессом `telegram-bridge.py`.

### Проверка исходящих к Telegram

```bash
# токен из /etc/telegram-bridge.env
curl "https://api.telegram.org/bot<TELEGRAM_API_TOKEN>/getMe"
```

Ожидается `"ok": true`.

### Проверка webhook (должен быть удалён)

```bash
curl "https://api.telegram.org/bot<TELEGRAM_API_TOKEN>/getWebhookInfo"
```

В ответе `"url": ""`. Если webhook активен — он блокирует `getUpdates` (ошибка 409),
снимите его:

```bash
curl "https://api.telegram.org/bot<TELEGRAM_API_TOKEN>/deleteWebhook"
```

### Проверка push-эндпоинта (локально на инстансе)

```bash
curl -X POST http://127.0.0.1:8080/push \
  -H "X-Bot-Secret: <BOT_SECRET>" \
  -H "Content-Type: application/json" \
  -d '{"chat_id": <ваш_telegram_chat_id>, "text": "Тест"}'
```

Ожидается `{"ok": true}` и приход сообщения в Telegram.

Проверка без секрета (ожидается `403`):

```bash
curl -X POST http://127.0.0.1:8080/push \
  -H "Content-Type: application/json" \
  -d '{"chat_id": 1, "text": "x"}'
```

---

## 9. Типовые операции

### Перезапуск / остановка

```bash
sudo systemctl restart telegram-bridge
sudo systemctl stop telegram-bridge
sudo systemctl disable telegram-bridge   # убрать из автозагрузки
```

### Обновление скрипта

```bash
sudo cp /path/to/telegram-bridge.py /usr/local/bin/telegram-bridge.py
sudo chmod +x /usr/local/bin/telegram-bridge.py
sudo systemctl restart telegram-bridge
```

### Проверка переменных окружения, которые видит процесс

```bash
sudo systemctl show telegram-bridge -p EnvironmentFiles
```

---

## 10. Устранение неполадок

- **`Failed to start` / сервис сразу падает** → смотрите `journalctl -u telegram-bridge -n 50`.
  Частая причина — не задан `TELEGRAM_API_TOKEN` или `BOT_SECRET` (демон завершается с кодом 2).
- **`getUpdates` даёт 409** → активный webhook; снимите его (`deleteWebhook`).
- **Push не доходит извне** → проверьте security group и `ufw` (см. п. 6), что порт открыт.
- **Сообщения в Telegram не приходят** → проверьте, что бот добавлен в канал/ЛС и у него
  права на отправку; проверьте логи (`journalctl -u telegram-bridge -f`).

---

## 11. Сопутствующая настройка на сайте

- В `../.env`/`../.env.local` сайта задать `TELEGRAM_BOT_SECRET` (совпадает с `BOT_SECRET` на AWS)
  и `TELEGRAM_BRIDGE_URL` (например `http://<ec2-ip>:8080/push`).
- На сайте публикации отправляются через `App\Service\TelegramService::publish()`
  (точка вызова настраивается отдельно).
