<?php
namespace Adminko\Admin\Table;

use Adminko\System;
use Adminko\Db\Db;

class PageTable extends BuilderTable
{
    protected function actionAddSave($redirect = true)
    {
        $this->checkPageParent();

        $primary_field = parent::actionAddSave(false);

        System::build();

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionCopySave($redirect = true)
    {
        $primary_field = parent::actionCopySave(false);

        $this->copyBlocks(System::id(), $primary_field);

        System::build();

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionEditSave($redirect = true)
    {
        $this->checkPageParent();

        parent::actionEditSave(false);

        System::build();

        if ($redirect) {
            $this->redirect();
        }
    }

    protected function actionDelete($redirect = true)
    {
        $blocks = Db::selectAll('
            select * from block where block_page = :block_page', array('block_page' => System::id()));

        parent::actionDelete(false);

        foreach ($blocks as $block) {
            Db::delete('block_param', array('block' => $block['block_id']));
        }

        System::build();

        if ($redirect) {
            $this->redirect();
        }
    }

    protected function checkPageParent()
    {
        if (init_string('page_parent')) {
            $this->fields['page_name']['errors'][] = 'require';
        } else if (init_string('page_name') !== '') {
            throw new \AlarmException('Ошибочное значение поля "' . $this->fields['page_name']['title'] . '".');
        }
    }
}
