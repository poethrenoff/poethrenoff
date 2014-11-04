<?php
namespace Adminko\Admin\Table;

use Adminko\System;

class LayoutTable extends BuilderTable
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

        $this->copyLayoutAreas(System::id(), $primary_field);

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }
}
