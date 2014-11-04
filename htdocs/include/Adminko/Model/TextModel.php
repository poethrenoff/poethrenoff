<?php
namespace Adminko\Model;

use Adminko\Db\Db;

class TextModel extends Model
{
    // Получение текста по тегу
    public function getByTag($text_tag) {
        $record = Db::selectRow('
            select * from text where text_tag = :text_tag',
                array('text_tag' => $text_tag));
        return $this->get($record['text_id'], $record);
    }
}