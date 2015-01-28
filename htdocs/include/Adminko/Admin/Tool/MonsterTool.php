<?php
namespace Adminko\Admin\Tool;

use Adminko\Date;
use Adminko\Db\Db;
use Adminko\Admin\Admin;

class MonsterTool extends Admin
{
    protected function actionIndex()
    {
        $records_header['monster_place'] = array('title' => 'Место');
        $records_header['monster_title'] = array('title' => 'Автор', 'type' => 'string', 'main' => 1);
        $records_header['monster_count'] = array('title' => 'Стихов', 'type' => 'int');
        $records_header['monster_date'] = array('title' => 'Дата', 'type' => 'datetime');
        
        $monster_list = Db::selectAll('select * from monster order by monster_count desc');
        
        foreach ($monster_list as $monster_index => $monster_item) {
            $monster_shift = $monster_item['monster_place'] - $monster_item['monster_place_old'];
            if ($monster_shift) {
                $monster_list[$monster_index]['monster_place'] = "<nobr>" . $monster_item['monster_place'] .
                    " <sup style=\"color: " . ($monster_shift < 0 ? 'green' : 'red') . "\">" .
                        ($monster_shift < 0 ? '+' : '-') . abs($monster_shift) . "</sup></nobr>";
            }
            
            $monster_progress = $monster_item['monster_count'] - $monster_item['monster_count_old'];
            if ($monster_progress) {
                $monster_list[$monster_index]['monster_count'] = "<nobr>" . $monster_item['monster_count'] .
                    " <sup style=\"color: " . ($monster_progress > 0 ? 'green' : 'red') . "\">" .
                        ($monster_progress > 0 ? '+' : '-') . abs($monster_progress) . "</sup></nobr>";
            }
            
            $monster_list[$monster_index]['monster_title'] = "<a href=\"" .
                $this->getUrl($monster_item) . "\" target=\"_blank\"" .
                ($monster_item['monster_login'] == 'poethrenoff' ? " id=\"me\"" : '') .
                ">{$monster_item['monster_title']}</a>";
            if (!$monster_item['monster_active']) {
                $monster_list[$monster_index]['monster_title'] = "<s>{$monster_list[$monster_index]['monster_title']}</s>";
            }
            
            $monster_list[$monster_index]['monster_date'] = "<nobr>" . Date::get($monster_item['monster_date'], 'long') . "</nobr>";
        }
        
        $this->view->assign('title', $this->object_desc['title'] . ' и <a href="#me">Поэт Хренов</a>');
        $this->view->assign('records', $monster_list);
        $this->view->assign('header', $records_header);
        $this->view->assign('counter', count($monster_list));

        $this->content = $this->view->fetch('admin/table');
    }

    protected function getUrl(&$monster_item)
    {
        return "http://stihi.ru/avtor/" . $monster_item['monster_login'];
    }
}
