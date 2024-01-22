<?php

namespace Adminko\Module;

use Adminko\Model\Model;
use Adminko\System;
use Telegram\Bot\Api;

class BotModule extends Module
{
    protected function actionIndex()
    {
        $telegram = new Api(TELEGRAM_API_TOKEN);
        $result = $telegram->getWebhookUpdate();

        $text = $result["message"]["text"] ?? null;
        $chat_id = $result["message"]["chat"]["id"] ?? null;

        if (!$chat_id) {
            System::notFound();
        }

        if ($text) {
            if ($text == "/start" || $text == "/help") {
                $reply = "Отправьте боту слово или фразу из стихотворения, которое хотите найти. " .
                    "Если таких стихотворений окажется несколько, бот выберет из них одно на свой вкус. " .
                    "Команда /random возвращает случайное стихотворение.";
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $reply]);
            } elseif ($text == "/random") {
                $work_item = Model::factory('work')->getWorkItem();
                $this->view->assign('work_item', $work_item);
                $reply = $this->view->fetch('module/work/bot');
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $reply]);
            } else {
                $search_list = Model::factory('work')->getListByText($text, 100, 0, true);
                if ($search_list) {
                    $this->view->assign('work_item', $search_list[array_rand($search_list)]);
                    $reply = $this->view->fetch('module/work/bot');
                    $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $reply]);
                } else {
                    $reply = "По запросу \"<b>".$text."</b>\" ничего не найдено";
                    $telegram->sendMessage(['chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $reply]);
                }
            }
        } else {
            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "Отправьте текстовое сообщение"]);
        }

        exit;
    }

    protected function getCacheKey()
    {
        return false;
    }
}
