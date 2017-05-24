<?php
namespace Adminko\Admin\Table;

use Adminko\Db\Db;
use Adminko\System;

class WorkTable extends Table
{
    protected function actionAddSave($redirect = true)
    {
        $primary_field = parent::actionAddSave(false);

        $work_title = init_string('work_title');
        if (is_empty($work_title)) {
            $work_text = init_string('work_text');
            $work_title = $this->getTitle($work_text);
            Db::update($this->object, array('work_title' => $work_title), array($this->primary_field => $primary_field));
        }

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionEditSave($redirect = true)
    {
        parent::actionEditSave(false);

        $work_title = init_string('work_title');
        if (is_empty($work_title)) {
            $work_text = init_string('work_text');
            $work_title = $this->getTitle($work_text);
            Db::update($this->object, array('work_title' => $work_title), array($this->primary_field => System::id()));
        }

        if ($redirect) {
            $this->redirect();
        }
    }

    protected function getTitle($work_text)
    {
        $work_text_list = explode("\n", $work_text);
        $work_title = trim($work_text_list[0], " .,…;:!?\r\n-–");

        return "\"{$work_title}...\"";
    }
}
