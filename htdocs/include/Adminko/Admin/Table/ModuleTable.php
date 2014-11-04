<?php
namespace Adminko\Admin\Table;

use Adminko\System;
use Adminko\Db\Db;

class ModuleTable extends BuilderTable
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

        $this->copyModuleParams(System::id(), $primary_field);

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionDelete($redirect = true)
    {
        $module_params = Db::selectAll('
            select * from module_param where param_module = :param_module', array('param_module' => System::id()));

        parent::actionDelete(false);

        foreach ($module_params as $module_param) {
            Db::delete('param_value', array('value_param' => $module_param['param_id']));
        }

        System::build();

        if ($redirect) {
            $this->redirect();
        }
    }
}
