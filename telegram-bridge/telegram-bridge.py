#!/usr/bin/env python3
"""
Telegram bridge daemon for AWS EC2.

Long-polls Telegram for updates (getUpdates) and forwards each update to the
PoetHrenoff site gateway (POST /bot), which replies with the generated texts.
The daemon then sends each reply back to the originating chat.

Because the production hosting blocks both outbound calls to Telegram and
incoming webhooks from Telegram, this daemon runs on AWS EC2 (which has access
to both Telegram and poethrenoff.ru) and acts as the relay.

Environment variables:
    TELEGRAM_API_TOKEN  Telegram bot token (from BotFather).
    SITE_URL            Site gateway endpoint, e.g. https://poethrenoff.ru/bot.
    BOT_SECRET          Shared secret sent via X-Bot-Secret header.
    PUSH_HOST           Optional. Bind address for the push HTTP listener
                        (default: 0.0.0.0).
    PUSH_PORT           Optional. Port for the push HTTP listener (default: 8080).

The daemon also exposes a small HTTP endpoint, POST /push, so the site can
publish messages to Telegram on its own initiative. Body: {"chat_id": ...,
"text": "..."} plus the X-Bot-Secret header.

Run as a systemd service (see telegram-bridge.service). Logs to stdout/journald.
"""

import json
import os
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import requests

TELEGRAM_API_BASE = "https://api.telegram.org/bot{}"

POLL_TIMEOUT = 30
POLL_LIMIT = 100
BACKOFF_START = 1
BACKOFF_MAX = 60

SITE_TIMEOUT = 20
SITE_CONNECT_TIMEOUT = 10
SITE_RETRIES = 3
SITE_RETRY_BACKOFF_START = 1
SITE_RETRY_BACKOFF_MAX = 8

SESSION = None


def api_url(method: str) -> str:
    return TELEGRAM_API_BASE.format(TOKEN) + "/" + method


def log(message: str) -> None:
    print(message, flush=True)


def send_message(chat_id, text: str) -> None:
    response = SESSION.post(
        api_url("sendMessage"),
        json={"chat_id": chat_id, "text": text, "parse_mode": "HTML"},
        timeout=30,
    )
    response.raise_for_status()
    payload = response.json()
    if not payload.get("ok"):
        raise RuntimeError("sendMessage error: {}".format(payload))


class PushHandler(BaseHTTPRequestHandler):
    def do_POST(self):  # noqa: N802
        if self.path != "/push":
            self._reply(404, {"error": "Not found"})
            return

        secret = self.headers.get("X-Bot-Secret")
        if secret != BOT_SECRET:
            self._reply(403, {"error": "Forbidden"})
            return

        try:
            length = int(self.headers.get("Content-Length", "0"))
            body = json.loads(self.rfile.read(length))
            chat_id = body.get("chat_id")
            text = body.get("text")
            if chat_id is None or not isinstance(text, str) or not text:
                self._reply(400, {"error": "chat_id and text are required"})
                return
            send_message(chat_id, text)
        except Exception as exc:  # noqa: BLE001
            log("push error: {}".format(exc))
            self._reply(500, {"error": "Internal error"})
            return

        log("push to chat {}: {}".format(chat_id, text))
        self._reply(200, {"ok": True})

    def _reply(self, status: int, payload: dict) -> None:
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format: str, *args) -> None:  # noqa: A002
        log("http: " + format % args)


def run_push_server() -> None:
    server = ThreadingHTTPServer((PUSH_HOST, PUSH_PORT), PushHandler)
    log("Push listener started on {}:{}".format(PUSH_HOST, PUSH_PORT))
    server.serve_forever()


def get_updates(offset: int) -> list:
    response = SESSION.get(
        api_url("getUpdates"),
        params={"offset": offset, "limit": POLL_LIMIT, "timeout": POLL_TIMEOUT},
        timeout=POLL_TIMEOUT + 15,
    )
    response.raise_for_status()
    payload = response.json()
    if not payload.get("ok"):
        raise RuntimeError("getUpdates error: {}".format(payload))
    return payload.get("result", [])


def forward_to_site(update: dict) -> list:
    backoff = SITE_RETRY_BACKOFF_START
    for attempt in range(SITE_RETRIES):
        try:
            response = SESSION.post(
                SITE_URL,
                headers={"X-Bot-Secret": BOT_SECRET, "Content-Type": "application/json"},
                data=json.dumps(update),
                timeout=(SITE_CONNECT_TIMEOUT, SITE_TIMEOUT),
            )
            if response.status_code != 200:
                raise RuntimeError(
                    "site gateway error {}: {}".format(response.status_code, response.text)
                )
            return response.json().get("replies", [])
        except Exception as exc:
            if (
                attempt < SITE_RETRIES - 1
                and isinstance(exc, (requests.ConnectionError, requests.Timeout))
            ):
                log(
                    "site request failed (attempt {}): {}; retrying in {}s".format(
                        attempt + 1, exc, backoff
                    )
                )
                time.sleep(backoff)
                backoff = min(backoff * 2, SITE_RETRY_BACKOFF_MAX)
                continue
            raise


def extract_chat_id(update: dict):
    return ((update.get("message") or {}).get("chat") or {}).get("id")


def run() -> None:
    offset = 0
    backoff = BACKOFF_START

    log("Telegram bridge started (site: {})".format(SITE_URL))

    while True:
        try:
            updates = get_updates(offset)
            backoff = BACKOFF_START

            for update in updates:
                update_id = update.get("update_id")
                try:
                    chat_id = extract_chat_id(update)
                    replies = forward_to_site(update)
                    if chat_id is None:
                        log("skip update {}: no chat_id".format(update_id))
                    else:
                        for reply in replies:
                            send_message(chat_id, reply)
                except Exception as exc:  # noqa: BLE001
                    log("error processing update {}: {}".format(update_id, exc))

                if isinstance(update_id, int):
                    offset = update_id + 1
        except Exception as exc:  # noqa: BLE001
            log("polling error: {}".format(exc))
            time.sleep(backoff)
            backoff = min(backoff * 2, BACKOFF_MAX)


def main() -> None:
    global TOKEN, SITE_URL, BOT_SECRET, PUSH_HOST, PUSH_PORT, SESSION

    TOKEN = os.environ.get("TELEGRAM_API_TOKEN")
    SITE_URL = os.environ.get("SITE_URL", "https://poethrenoff.ru/bot")
    BOT_SECRET = os.environ.get("BOT_SECRET")
    PUSH_HOST = os.environ.get("PUSH_HOST", "0.0.0.0")
    PUSH_PORT = int(os.environ.get("PUSH_PORT", "8080"))

    missing = [
        name
        for name, value in (
            ("TELEGRAM_API_TOKEN", TOKEN),
            ("BOT_SECRET", BOT_SECRET),
        )
        if not value
    ]
    if missing:
        sys.stderr.write(
            "Missing required environment variable(s): {}\n".format(", ".join(missing))
        )
        sys.exit(2)

    SESSION = requests.Session()

    threading.Thread(target=run_push_server, daemon=True).start()
    run()


if __name__ == "__main__":
    main()
