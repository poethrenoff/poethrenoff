<?php
namespace Adminko\Module;

use Adminko\System;
use Adminko\Model\Model;

class MenuModule extends Module
{
    protected function actionIndex()
    {
        $menu_id = $this->getParam('id');
        $menu_template = $this->getParam('template');
        
        $menu_list = Model::factory('menu')->getList(
            array('menu_active' => 1), array('menu_order' => 'asc')
        );
        
        $site = System::site(); $current_page = System::page();
        $page_list = array_reindex($site['page'], 'page_id');
        foreach ($menu_list as $menu_index => $menu_item) {
            if (isset($page_list[$menu_item->getMenuPage()])) {
                $menu_url = $page_list[$menu_item->getMenuPage()]['page_path'];
                $menu_list[$menu_index]->setMenuUrl($menu_url);
            }
            if ($menu_list[$menu_index]->getMenuUrl() == $current_page['page_path']) {
                $menu_list[$menu_index]->setSelected(true);
            }
        }
        
        $menu_tree = Model::factory('menu')->getTree($menu_list, $menu_id);
        
        $this->view->assign('menu_tree', $menu_tree);
        $this->content = $this->view->fetch('module/menu/' . $menu_template);
    }
    
    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    // Дополнительные параметры хэша модуля
    protected function extCacheKey()
    {
        $current_page = System::page();
        return parent::extCacheKey() +
            array('_page' => $current_page['page_id']);
    }
}