<?php
namespace Adminko\Admin\Table;

use Adminko\System;

class ModuleParamTable extends BuilderTable
{
    protected function actionAddSave($redirect = true)
    {
        $this->checkParamDefault();

        $primary_field = parent::actionAddSave(false);

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionCopySave($redirect = true)
    {
        $primary_field = parent::actionCopySave(false);

        $this->copyParamValues(System::id(), $primary_field);

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionEditSave($redirect = true)
    {
        $this->checkParamDefault();

        parent::actionEditSave(false);

        System::build();

        if ($redirect) {
            $this->redirect();
        }
    }

    protected function checkParamDefault()
    {
        if (init_string('param_require') &&
                !in_array(init_string('param_type'), array('select', 'table', 'boolean'))) {
            $this->fields['param_default']['errors'][] = 'require';
        }

        if (init_string('param_type') == 'boolean') {
            $this->fields['param_default']['type'] = 'boolean';
        }
        if (init_string('param_type') == 'int') {
            $this->fields['param_default']['type'] = 'int';
        }

        if (init_string('param_type') == 'table' && !isset(Metadata::$objects[init_string('param_table')])) {
            throw new \AlarmException('Ошибочное значение поля "' . $this->fields['param_table']['title'] . '".');
        }
    }

    protected function getCardScripts($action = 'edit', $record = null)
    {
        $scripts = parent::getCardScripts($action, $record);

        $scripts['module_param_card'] = '';

        return $scripts;
    }
}
