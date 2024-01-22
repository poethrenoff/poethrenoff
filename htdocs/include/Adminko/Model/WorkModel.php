<?php
namespace Adminko\Model;

use Adminko\System;
use Adminko\Db\Db;

class WorkModel extends Model
{
    protected $filter_cond = array();

    protected $filter_bind = array();

    protected $order = array();

    protected $limit = null;

    protected $offset = null;

    protected function setDefaultCondition()
    {
        $this->filter_cond = array();
        $this->filter_bind = array();
        
        $this->filter_cond[] = 'work_active = :work_active';
        $this->filter_bind['work_active'] = 1;

        $this->filter_cond[] = 'group_active = :group_active';
        $this->filter_bind['group_active'] = 1;

        return $this;
    }

    protected function setGroupCondition($work_group)
    {
        $this->filter_cond[] = 'work_group = :work_group';
        $this->filter_bind['work_group'] = $work_group;

        return $this;
    }

    protected function setGroupParentCondition($group_parent)
    {
        $this->filter_cond[] = 'group_parent = :group_parent';
        $this->filter_bind['group_parent'] = $group_parent;

        return $this;
    }

    protected function setWorkCondition($work_id)
    {
        $this->filter_cond[] = 'work_id = :work_id';
        $this->filter_bind['work_id'] = $work_id;

        return $this;
    }

    protected function setOrderCondition($work_order, $direction)
    {
        $this->filter_cond[] = 'work_order ' . ($direction ? '>' : '<') . ' :work_order';
        $this->filter_bind['work_order'] = $work_order;

        return $this;
    }

    protected function setTextCondition($search_text)
    {
        $search_words = $search_text !== '' ? preg_split('/\s+/isu', $search_text) : array();
        foreach ($search_words as $word_index => $word_value) {
            $filter_word_cond = array();
            foreach (array('work_title', 'work_text', 'work_comment') as $field_name) {
                $index_name = $field_name . '_' . $word_index;
                $filter_word_cond[] = 'lower(' . $field_name . ') like lower(:' . $index_name . ')';
                $this->filter_bind[$index_name] = '%' . $word_value . '%';
            }
            $this->filter_cond[] = '(' . join(' or ', $filter_word_cond) . ')';
        }

        return $this;
    }

    protected function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    protected function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    protected function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    protected function getCountQuery()
    {
        $filter_clause = 'where ' . join(' and ', $this->filter_cond);

        $work_query = "
            select count(*) from work
                inner join work_group on work_group = group_id
            {$filter_clause}";
        
        return $work_query;
    }

    protected function getListQuery()
    {
        $filter_clause = 'where ' . join(' and ', $this->filter_cond);
        
        $order_clause = $this->getOrderClause($this->order);
        $limit_clause = $this->getLimitClause($this->limit, $this->offset);

        $work_query = "
            select work.* from work
                inner join work_group on work_group = group_id
            {$filter_clause} {$order_clause} {$limit_clause}";
        
        return $work_query;
    }    
    
    public function getWorkList($group_id)
    {
        $work_query = $this
            ->setDefaultCondition()
            ->setGroupCondition($group_id)
            ->setOrder(array('work_order' => 'asc'))
            ->getListQuery();
        $work_list = Db::selectAll($work_query, $this->filter_bind);
        
        return $this->getBatch($work_list);
    }
    
    public function getWorkItem($work_id = 0)
    {
        if ($work_id) {
            $work_query = $this
                ->setDefaultCondition()
                ->setWorkCondition($work_id)
                ->getListQuery();
        } else {
            $work_query = $this
                ->setDefaultCondition()
                ->setGroupParentCondition(66) // Идентификатор группы "Главное"
                ->setOrder(array('rand()' => 'asc'))
                ->getListQuery();
        }
        $work_item = Db::selectRow($work_query, $this->filter_bind);
        
        if (!$work_item) {
            throw new \AlarmException('Произведение не найдено');
        }
        
        return Model::factory('work')->get($work_item['work_id'], $work_item);
    }
    
    public function getNextWork()
    {
        return $this->getSiblingWork(true);
    }
    
    public function getPrevWork()
    {
        return $this->getSiblingWork(false);
    }
    
    public function getSiblingWork($direction)
    {
        if ($this->is_new) {
            return false;
        }
        
        $work_query = $this
            ->setDefaultCondition()
            ->setGroupCondition($this->getWorkGroup())
            ->setOrderCondition($this->getWorkOrder(), $direction)
            ->setOrder(array('work_order' => $direction ? 'asc' : 'desc'))
            ->getListQuery();
        $work_item = Db::selectRow($work_query, $this->filter_bind);
        
        if (!$work_item) {
            return false;
        }
        
        return Model::factory('work')->get($work_item['work_id'], $work_item);
    }

    public function getCountByText($search_text)
    {
        $work_query = $this
            ->setDefaultCondition()
            ->setTextCondition($search_text)
            ->getCountQuery();
        return Db::selectCell($work_query, $this->filter_bind);
    }
    
    public function getListByText($search_text, $limit, $offset, $mainOnly = false)
    {
        $work_condition = $this
            ->setDefaultCondition()
            ->setTextCondition($search_text)
            ->setOrder(array('work_id' => 'desc'))
            ->setLimit($limit)
            ->setOffset($offset);

        if ($mainOnly) {
            $work_condition->setGroupParentCondition(66); // Идентификатор группы "Главное"
        }

        $work_query = $work_condition->getListQuery();
        $work_list = Db::selectAll($work_query, $this->filter_bind);
        
        return $this->getBatch($work_list);
    }
    
    public function getViewText()
    {
        return preg_replace_callback('/^ +| {2,}/m', function($matches) {
            return str_repeat( '&nbsp;', strlen($matches[    0]) );
        }, $this->getWorkText());
    }
    
    public function getWorkUrl()
    {
        return System::urlFor(array('controller' => 'work', 'action' => 'view', 'id' => $this->getId()));
    }
}