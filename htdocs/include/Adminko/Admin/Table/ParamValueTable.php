<?php
namespace Adminko\Admin\Table;

use Adminko\System;
use Adminko\Db\Db;

class ParamValueTable extends BuilderTable
{
    protected function actionAddSave($redirect = true)
    {
        $primary_field = parent::actionAddSave(false);

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionCopySave($redirect = true)
    {
        $primary_field = parent::actionCopySave(false);

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionDelete($redirect = true)
    {
        $record = $this->getRecord();
        $primary_field = $record[$this->primary_field];

        parent::actionDelete(false);

        $default_value = Db::selectCell('select value_id from param_value
            where value_param = :value_param and value_default = 1', array('value_param' => $record['value_param']));

        Db::update('block_param', array('value' => $default_value), array('param' => $record['value_param'], 'value' => $primary_field));

        System::build();

        if ($redirect) {
            $this->redirect();
        }
    }
}
