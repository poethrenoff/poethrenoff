<?php

namespace Adminko\Module;

use Adminko\Model\Model;
use Telegram\Bot\Api;

class BotModule extends Module
{
    protected function actionIndex()
    {
        $telegram = new Api(TELEGRAM_API_TOKEN);
        $result = $telegram->getWebhookUpdate();

        $text = $result["message"]["text"];
        $chat_id = $result["message"]["chat"]["id"];

        if ($text) {
            if ($text == "/start" || $text == "/help") {
                $reply = "Вы можете управлять ботом, отправляя команды:\n\n/random — случайное стихотворение";
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $reply]);
            } elseif ($text == "/random") {
                $work_item = Model::factory('work')->getWorkItem();
                $this->view->assign('work_item', $work_item);
                $reply = $this->view->fetch('module/work/bot');
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $reply]);
            } else {
                $reply = "По запросу \"<b>".$text."</b>\" ничего не найдено";
                $telegram->sendMessage(['chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $reply]);
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
