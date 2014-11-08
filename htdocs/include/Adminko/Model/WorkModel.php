<?php
namespace Adminko\Model;

use Adminko\System;
use Adminko\Db\Db;

class WorkModel extends Model
{
    public function getWorkList($group_id)
    {
        $work_cond = array(
            'work_active = :work_active',
            'group_active = :group_active',
            'work_group = :work_group'
        );
        $work_bind = array(
            'work_active' => 1,
            'group_active' => 1,
            'work_group' => $group_id
        );
                
        $order_clause = "order by work_order";
        
        $work_query = '
            select work.* from work
                inner join work_group on work_group = group_id
            where ' . join(' and ', $work_cond) . '
            ' . $order_clause;
        $work_list = Db::selectAll($work_query, $work_bind);
        
        return $this->getBatch($work_list);
    }
    
    public function getWorkItem($work_id = 0)
    {
        $work_cond = array(
            'work_active = :work_active',
            'group_active = :group_active'
        );
        $work_bind = array(
            'work_active' => 1,
            'group_active' => 1
        );
        
        if ($work_id) {
            $work_cond[] = 'work_id = :work_id';
            $work_bind['work_id'] = $work_id;
        }
        
        $order_clause = !$work_id ? 'order by rand()' : '';
        
        $work_query = '
            select work.* from work
                inner join work_group on work_group = group_id
            where ' . join(' and ', $work_cond) . '
            ' . $order_clause;
        $work_item = Db::selectRow($work_query, $work_bind);
        
        if (!$work_item) {
            throw new \AlarmException('Произведение не найдено');
        }
        
        $work_item['work_text'] = preg_replace_callback('/^ +| {2,}/m', function($matches) {
            return str_repeat( '&nbsp;', strlen($matches[0]) );
        }, $work_item['work_text']);
        
        return Model::factory('work')->get($work_item['work_id'], $work_item);
    }
    
    public function getWorkUrl()
    {
        return System::urlFor(array('controller' => 'work', 'action' => 'view', 'id' => $this->getId()));
    }
}