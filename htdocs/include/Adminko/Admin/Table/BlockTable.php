<?php
namespace Adminko\Admin\Table;

use Adminko\System;
use Adminko\Db\Db;
use Adminko\Field\Field;

class BlockTable extends BuilderTable
{
    protected function actionAddSave($redirect = true)
    {
        $primary_field = parent::actionAddSave(false);

        $this->applyDefaultParams($primary_field);

        System::build();

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionCopySave($redirect = true)
    {
        $primary_field = parent::actionCopySave(false);

        $this->copyBlockParams(System::id(), $primary_field);

        System::build();

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionEditSave($redirect = true)
    {
        $record = $this->getRecord();
        $primary_field = $record[$this->primary_field];

        parent::actionEditSave(false);

        if ($record['block_module'] != init_string('block_module')) {
            $this->applyDefaultParams($primary_field);
        }

        System::build();

        if ($redirect) {
            $this->redirect();
        }
    }

    protected function actionParam()
    {
        $record = $this->getRecord();
        $params = $this->getParams($record[$this->primary_field]);

        $form_fields = array();
        foreach ($params as $param_index => $param_value) {
            $param_errors = array();
            if ($param_value['param_require']) {
                $param_errors[] = 'require';
            }
            if ($param_value['param_type'] == 'int') {
                $param_errors[] = 'int';
            }

            $form_fields['param[' . $param_value['param_id'] . ']'] = array(
                'title' => $param_value['param_title'],
                'type' => $param_value['param_type'],
                'errors' => $param_errors,
                'require' => $param_value['param_require'] ? 'require' : '',
                'value' => field::factory($param_value['param_type'])->set($param_value['value'])->form());
            if ($param_value['param_type'] == 'select') {
                $values = Db::selectAll('
                    select * from param_value
                    where value_param = :value_param
                    order by value_title', array('value_param' => $param_value['param_id']));

                $value_records = array();
                foreach ($values as $value) {
                    $value_records[] = array('value' => (string) $value['value_id'], 'title' => Field::factory('string')->set($value['value_title'])->form());
                }

                $form_fields['param[' . $param_value['param_id'] . ']']['values'] = $value_records;
            }
            if ($param_value['param_type'] == 'table') {
                $form_fields['param[' . $param_value['param_id'] . ']']['values'] = $this->getTableRecords($param_value['param_table']);
            }
        }

        $record_title = $record[$this->main_field];
        $action_title = 'Параметры блока';

        $this->view->assign('record_title', $this->object_desc['title'] . ' :: ' . $record_title);
        $this->view->assign('action_title', $action_title);
        $this->view->assign('fields', $form_fields);

        $form_url = System::urlFor(array('object' => $this->object, 'action' => 'param_save', 'id' => $record[$this->primary_field]));
        $this->view->assign('form_url', $form_url);

        $prev_url = $this->restoreState();
        $this->view->assign('back_url', System::urlFor($prev_url));

        $this->content = $this->view->fetch('/admin/form');
        $this->output['meta_title'] .= ' :: ' . $record_title . ' :: ' . $action_title;
    }

    protected function actionParamSave($redirect = true)
    {
        $record = $this->getRecord();
        $params = $this->getParams($record[$this->primary_field]);

        $param_values = init_array('param');

        $insert_fields = array();
        foreach ($params as $param_index => $param_value) {
            $param_errors = array();
            if ($param_value['param_require']) {
                $param_errors[] = 'require';
            }
            if ($param_value['param_type'] == 'int') {
                $param_errors[] = 'int';
            }

            $value_content = isset($param_values[$param_value['param_id']]) ?
                $param_values[$param_value['param_id']] : '';

            $field = Field::factory($param_value['param_type'])->set($value_content);
            if (!($field->check($param_errors))) {
                throw new \AlarmException('Ошибочное значение поля "' . $param_value['param_title'] . '".');
            }
            $insert_fields[$param_value['param_id']] = $field->get();
        }

        Db::delete('block_param', array('block' => $record['block_id']));
        foreach ($insert_fields as $param_id => $param_value) {
            Db::insert('block_param', array(
                'block' => $record['block_id'], 'param' => $param_id, 'value' => $param_value));
        }

        System::build();

        if ($redirect) {
            $this->redirect();
        }
    }

    protected function actionMultiply($redirect = true)
    {
        $record = $this->getRecord();

        $page_list = Db::selectAll('
            select * from page, layout_area
            where page.page_layout = layout_area.area_layout and
                layout_area.area_id = :block_area and
                page.page_id <> :block_page and page.page_folder <> 1', array('block_area' => $record['block_area'], 'block_page' => $record['block_page']));

        $insert_block = $record;
        unset($insert_block['block_id']);
        $insert_block_params = Db::selectAll('select param, value from block_param where block = :block_id', array('block_id' => $record['block_id']));

        foreach ($page_list as $page) {
            $block = Db::selectRow('select * from block where block_page = :block_page and block_area = :block_area', array('block_area' => $insert_block['block_area'], 'block_page' => $page['page_id']));

            if ($block) {
                Db::update('block', array_merge($insert_block, array('block_page' => $page['page_id'])), array('block_id' => $block['block_id']));
                $block_id = $block['block_id'];
            } else {
                Db::insert('block', array_merge($insert_block, array('block_page' => $page['page_id'])));
                $block_id = Db::lastInsertId();
            }

            Db::delete('block_param', array('block' => $block['block_id']));
            foreach ($insert_block_params as $insert_block_param) {
                Db::insert('block_param', array_merge($insert_block_param, array('block' => $block_id)));
            }
        }

        System::build();

        if ($redirect) {
            $this->redirect();
        }
    }

    protected function getParams($block_id)
    {
        return Db::selectAll('
            select module_param.*, block_param.value
            from module_param
                inner join block on block.block_module = module_param.param_module
                left join block_param on block_param.param = module_param.param_id and
                    block_param.block = block.block_id
            where block.block_id = :block_id
            order by module_param.param_order', array('block_id' => $block_id));
    }

    protected function applyDefaultParams($block_id)
    {
        $params = $this->getParams($block_id);

        $params_in = array_make_in($params, 'param_id');
        $param_values = Db::selectAll('select * from param_value where value_param in (' . $params_in . ') order by value_default');
        $param_values = array_reindex($param_values, 'value_param');

        $insert_fields = array();
        foreach ($params as $param_index => $param_value) {
            if ($param_value['param_type'] == 'select') {
                $value = Db::selectCell('select value_id from param_value
                    where value_param = :value_param and value_default = 1', array('value_param' => $param_value['param_id']));
            } else {
                $value = $param_value['param_default'];
            }

            $field = field::factory($param_value['param_type'])->set($value);
            $insert_fields[$param_value['param_id']] = $field->get();
        }

        Db::delete('block_param', array('block' => $block_id));
        foreach ($insert_fields as $param_id => $param_value) {
            Db::insert('block_param', array(
                'block' => $block_id, 'param' => $param_id, 'value' => $param_value));
        }
    }

    protected function getRecordActions($record)
    {
        $actions = parent::getRecordActions($record);

        $actions['property'] = array('title' => 'Параметры', 'url' =>
            System::urlFor(array('object' => $this->object, 'action' => 'param', 'id' => $record[$this->primary_field])));
        $actions['multiply'] = array('title' => 'Размножить блок', 'url' =>
            System::urlFor(array('object' => $this->object, 'action' => 'multiply', 'id' => $record[$this->primary_field])),
                'event' => array('method' => 'onclick', 'value' => 'return confirm(\'Вы действительно хотите размножить этот блок?\')'));

        return $actions;
    }

    protected function getCardScripts($action = 'edit', $record = null)
    {
        $scripts = parent::getCardScripts($action, $record);

        $page_list = Db::selectAll('select page_id, page_layout from page, layout where page_layout = layout_id');
        $area_list = Db::selectAll('select area_id, area_title, area_layout from layout_area, layout where area_layout = layout_id order by area_title');

        $scripts['block_card'] = json_encode(array('page_list' => $page_list, 'area_list' => $area_list));

        return $scripts;
    }
}
