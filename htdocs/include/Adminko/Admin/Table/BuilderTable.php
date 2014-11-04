<?php
namespace Adminko\Admin\Table;

use Adminko\Db\Db;
use Adminko\System;

class BuilderTable extends Table
{
    protected function actionAddSave($redirect = true)
    {
        $primary_field = parent::actionAddSave(false);

        if ($redirect) {
            System::build();
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionCopySave($redirect = true)
    {
        $primary_field = parent::actionCopySave(false);

        if ($redirect) {
            System::build();
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionEditSave($redirect = true)
    {
        parent::actionEditSave(false);

        if ($redirect) {
            System::build();
            $this->redirect();
        }
    }

    protected function actionMove($redirect = true)
    {
        parent::actionMove(false);

        if ($redirect) {
            System::build();
            $this->redirect();
        }
    }

    protected function actionDelete($redirect = true)
    {
        parent::actionDelete(false);

        if ($redirect) {
            System::build();
            $this->redirect();
        }
    }

    protected function actionShow($redirect = true)
    {
        parent::actionShow(false);

        if ($redirect) {
            System::build();
            $this->redirect();
        }
    }

    protected function actionHide($redirect = true)
    {
        parent::actionHide(false);

        if ($redirect) {
            System::build();
            $this->redirect();
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////

    protected function copyBlocks($from_id, $to_id)
    {
        $blocks = Db::selectAll('
            select * from block where block_page = :block_page', array('block_page' => $from_id));

        foreach ($blocks as $block) {
            $block_id = $block['block_id'];

            unset($block['block_id']);
            $block['block_page'] = $to_id;

            Db::insert('block', $block);

            $this->copyBlockParams($block_id, Db::lastInsertId());
        }
    }

    protected function copyBlockParams($from_id, $to_id)
    {
        $block_params = Db::selectAll('
            select * from block_param where block = :block', array('block' => $from_id));

        foreach ($block_params as $block_param) {
            $block_param['block'] = $to_id;

            Db::insert('block_param', $block_param);
        }
    }

    protected function copyModuleParams($from_id, $to_id)
    {
        $module_params = Db::selectAll('
            select * from module_param where param_module = :param_module', array('param_module' => $from_id));

        foreach ($module_params as $module_param) {
            $module_param_id = $module_param['param_id'];

            unset($module_param['param_id']);
            $module_param['param_module'] = $to_id;

            Db::insert('module_param', $module_param);

            $this->copyParamValues($module_param_id, Db::lastInsertId());
        }
    }

    protected function copyParamValues($from_id, $to_id)
    {
        $param_values = Db::selectAll('
            select * from param_value where value_param = :value_param', array('value_param' => $from_id));

        foreach ($param_values as $param_value) {
            unset($param_value['value_id']);
            $param_value['value_param'] = $to_id;

            Db::insert('param_value', $param_value);
        }
    }

    protected function copyLayoutAreas($from_id, $to_id)
    {
        $layout_areas = Db::selectAll('
            select * from layout_area where area_layout = :area_layout', array('area_layout' => $from_id));

        foreach ($layout_areas as $layout_area) {
            unset($layout_area['area_id']);
            $layout_area['area_layout'] = $to_id;

            Db::insert('layout_area', $layout_area);
        }
    }
}
