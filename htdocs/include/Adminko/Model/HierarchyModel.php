<?php
namespace Adminko\Model;

use Adminko\Metadata;

class HierarchyModel extends Model
{
    // Поле с идентификатором родительской записи
    protected $parent_field = '';
    
    // Родительский объект
    protected $parent = null;
    
    // Массив дочерних объектов
    protected $children = array();
    
    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    public function __construct($object)
    {
        parent::__construct($object);
        
        $object_desc = Metadata::$objects[$object];
        foreach ($object_desc['fields'] as $field_name => $field_desc) {
            if ($field_desc['type'] == 'parent') {
                $this->parent_field = $field_name;
            }
        }
        if (!$this->parent_field) {
            throw new \AlarmException('Ошибка в описании таблицы "' . $object . '". Отсутствует поле родительской записи.');
        }
    }

    // Получение идентификатора родительской записи
    public function getParentId()
    {
        return $this->fields[$this->parent_field];
    }
    
    // Получение поля с идентификатором родительской записи
    public function getParentField()
    {
        return $this->parent_field;
    }
    
    // Получение объекта-родителя
    public function getParent()
    {
        return $this->parent;
    }
    
    // Получение списка дочерних объектов
    public function getChildren()
    {
        return $this->children;
    }
    
    // Количество дочерних объектов
    public function getChildrenCount()
    {
        return count($this->children);
    }
    
    // Есть ли дочерние объекты
    public function hasChildren()
    {
        return $this->getChildrenCount() > 0;
    }
    
    // Построение дерева записей
    public function getTree(&$records, $root_field = 0, $except = array())
    {
        $root_parent = null;
        
        $parent_method = 'get' . to_class_name($this->parent_field);
        $primary_method = 'get' . to_class_name($this->primary_field);
        
        if (!$root_field) {
            $records[] = Model::factory($this->object); $except[] = 0;
        }
        
        foreach ($records as $parent_record) {
            foreach ($records as $child_record) {
                if ($child_record->$parent_method() == (int)$parent_record->$primary_method() &&
                        !in_array((int)$child_record->$primary_method(), $except)) {
                    $child_record->parent = $parent_record;
                    $parent_record->children[] = $child_record;
                }
            }
            if ((int)$parent_record->$primary_method() == $root_field) {
                $root_parent = $parent_record;
            }
        }
        return $root_parent;
    }
}