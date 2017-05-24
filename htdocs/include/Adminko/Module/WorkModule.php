<?php
namespace Adminko\Module;

use Adminko\System;
use Adminko\Model\Model;
use Adminko\Cache\Cache;

class WorkModule extends Module
{
    // Текущее произведение
    private static $work = null;
    
    // Текущий раздел
    private static $work_group = null;
    
    // Вывод списка разделов
    protected function actionIndex()
    {
        $this->displayGroup();
    }

    // Вывод списка подразделов
    protected function actionGroup()
    {
        $group_item = $this->getGroupItem();
        $this->output['meta_title'] = SITE_TITLE . ' :: ' . $group_item->getGroupTitle();
        $this->displayGroup($group_item->getId());
    }

    // Вывод произведения
    protected function actionView()
    {
        $work_item = $this->getWorkItem();
        $this->view->assign('work_item', $work_item);
        $this->output['meta_title'] = SITE_TITLE . ' :: ' . $work_item->getWorkTitle();
        $this->content = $this->view->fetch('module/work/view');
    }

    // Вывод случайного произведения
    protected function actionRandom()
    {
        $this->actionView();
    }

    // Хлебные крошки
    protected function actionPath()
    {
        if (System::action() == 'index') {
            $group_id = 0;
        } elseif (System::action() == 'group') {
            $group_item = $this->getGroupItem();
            $group_id = $group_item->getId();
        } else {
            $work_item = $this->getWorkItem();
            $group_id = $work_item->getWorkGroup();
        }

        $group_path = $this->getGroupPath($group_id);

        $this->view->assign('group_path', $group_path);
        $this->content = $this->view->fetch('module/work/path');
    }
    
    // Получение произведения
    protected function getWorkItem()
    {
        if (is_null(self::$work)) {
            try {
                self::$work = Model::factory('work')->getWorkItem(System::id());
            } catch (\AlarmException $e) {
                System::notFound();
            }
        }
        return self::$work;
    }

    // Получение раздела
    protected function getGroupItem()
    {
        if (is_null(self::$work_group)) {
            try {
                self::$work_group = Model::factory('work_group')->get(System::id());
            } catch (\AlarmException $e) {
                System::notFound();
            }
        }
        return self::$work_group;
    }
    
    // Хлебные крошки
    protected function getGroupPath($group_parent = 0)
    {
        $cache_key = __METHOD__ . '(' . join(', ', func_get_args()) . ')';

        if (($group_path = Cache::get($cache_key)) !== false) {
            return $group_path;
        }
        
        $group_path = array();
        while (true) {
            try {
                $group_item = Model::factory('work_group')->get($group_parent);
            } catch (\AlarmException $e) {
                break;
            }
            $group_path[] = $group_item;
            $group_parent = $group_item->getGroupParent();
        }
        $group_path[] = Model::factory('work_group');
        
        $group_path = array_reverse($group_path);
        
        Cache::set($cache_key, $group_path);

        return $group_path;
    }
    
    // Вывод списка разделов
    protected function displayGroup($group_id = 0)
    {
        $group_list = Model::factory('work_group')->getList(array('group_active' => 1), array('group_order' => 'asc'));
        $group_tree = Model::factory('work_group')->getTree($group_list, $group_id);
        
        $work_list = Model::factory('work')->getWorkList($group_id);

        $this->view->assign('work_list', $work_list);
        $this->view->assign('group_tree', $group_tree);
        $this->content = $this->view->fetch('module/work/list');
    }

    // Вычисляет хэш параметров модуля
    protected function getCacheKey()
    {
        if ($this->action == 'view' || $this->action == 'random' || $this->action == 'path') {
            return false;
        }
        return parent::getCacheKey();
    }
    
    // Дополнительные параметры хэша модуля
    protected function extCacheKey()
    {
        return parent::extCacheKey() + array('_id' => System::id());
    }    
}
