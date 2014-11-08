<?php
namespace Adminko\Model;

use Adminko\Metadata;
use Adminko\Db\Db;
use Adminko\Field\Field;

class Model
{
    // Название таблицы
    protected $object = '';

    // Поле с идентификатором первичного ключа
    protected $primary_field = '';

    // Поля таблицы
    protected $fields = array();

    // Описание полей таблицы
    protected $fields_desc = array();

    // Вновь созданный объект
    protected $is_new = true;

    // Кеш объектов
    private static $object_cache = array();
    
    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    public function __construct($object)
    {
        if (!isset(metadata::$objects[$object])) {
            throw new \AlarmException('Ошибка. Объект не описан в метаданных.');
        }
        
        $object_desc = metadata::$objects[$object];
        if (!(isset($object_desc['fields']) && $object_desc['fields'])) {
            throw new \AlarmException('Ошибка. Объект не является таблицей.');
        }
        $this->object = $object;
        $this->fields_desc = $object_desc['fields'];
        
        foreach ($this->fields_desc as $field_name => $field_desc) {
            $this->fields[$field_name] = Field::factory($this->fields_desc[$field_name]['type']);
            if ($field_desc['type'] == 'pk') {
                $this->primary_field = $field_name;
            }
        }
        if (!$this->primary_field) {
            throw new \AlarmException('Ошибка в описании таблицы "' . $object . '". Отсутствует ключевое поле.');
        }
    }

    // Диспетчер неявных аксессоров
    public function __call($method, $vars) {
        if (!preg_match("/^(get|set)(\w+)/", $method, $matches)) {
            throw new \AlarmException("Ошибка. Метод " . get_called_class() . "::{$method}() не найден.");
        }
        
        $accessor = $matches[1];
        $field_name = to_field_name($matches[2]);
        $field_described = isset($this->fields_desc[$field_name]) && is_array($this->fields_desc[$field_name]);
        
        switch ($accessor) {
            case 'get':
                if ($field_described) {
                    return $this->fields[$field_name]->get();
                } else {
                    return $this->$field_name;
                }
            case 'set':
                if (!empty($vars)) {
                   if ($field_described) {
                       $this->fields[$field_name]->set($vars[0]);
                   } else {
                       $this->$field_name = $vars[0];
                   }
                }
                return $this;
        }
    }
    
    // Защита от несанкционированного доступа к полям
    public function __get($field_name){
        throw new \AlarmException("Ошибка. Поле " . get_called_class() . "::{$field_name} не найдено.");
    }
    // Защита от несанкционированного доступа к полям
    public function __set($field_name, $value){
        throw new \AlarmException("Ошибка. Поле " . get_called_class() . "::{$field_name} не найдено.");
    }

    // Создание объекта модели
    public static final function factory($object)
    {
        $class_namespace = 'Model';
        
        if (isset(Metadata::$objects[$object]['model'])) {
            $class_name = metadata::$objects[$object]['model'];
        } else {
            $class_name = to_class_name($object);
        }
        
        $class_name = __NAMESPACE__ . '\\' . $class_name . $class_namespace;
        if (!class_exists($class_name)) {
            $class_name = __NAMESPACE__ . '\\' . $class_namespace;
        }
        
        return new $class_name($object);
    }

    // Заполнение полей объекта из БД
    public function get($primary_field, $record = null) {
        if (!isset(self::$object_cache[$this->object][$primary_field])) {
            if (is_null($record)) {
                $record = Db::selectRow("select * from {$this->object} where {$this->primary_field} = :{$this->primary_field}",
                    array($this->primary_field => $primary_field)
                );
                if (!$record){
                    throw new \AlarmException("Ошибка. Запись {$this->object}({$primary_field}) не найдена.");
                }
            }
            foreach ($this->fields_desc as $field_name => $field_desc) {
                self::$object_cache[$this->object][$primary_field][$field_name] =
                    Field::factory($this->fields_desc[$field_name]['type'])->set($record[$field_name]);
            }
        }
        $this->fields = self::$object_cache[$this->object][$primary_field];
        $this->is_new = false;
        return $this;
    }

    // Получение списка объектов
    public function getBatch(&$records = array()) {
        $objects = array();
        foreach ($records as $record) {
            $objects[$record[$this->primary_field]] = Model::factory($this->object)->get($record[$this->primary_field], $record);
        }
        return $objects;
    }

    // Получение условия фильтрации записей
    public function getFilterCondition($where = array()) {
        $filter_conds = $filter_binds = array();
        foreach ($where as $name => $value) {
            if (is_numeric($name)) {
                $filter_conds[] = "{$value}";
            } else {
                $filter_conds[] = "{$name} = :{$name}";
                $filter_binds[$name] = $value;
            }
        }
        $filter_clause = $filter_conds ? 'where ' . join(' and ', $filter_conds) : '';
        return array($filter_clause, $filter_binds);
    }
    
    // Получение условия сортировки записей
    protected function getOrderClause($order = array()) {
        $order_conds = array();
        foreach ($order as $field => $dir) {
            $order_conds[] = "{$field} {$dir}";
        }
        return $order_conds ? 'order by ' . join(', ', $order_conds) : '';
    }
    
    // Получение условия ограничения количества записей
    protected function getLimitClause($limit = null, $offset = null) {
        $limit_clause = '';
        if (isset($limit)) {
            $limit_clause .= 'limit ' . $limit;
            if (isset($offset)) {
                $limit_clause .= ' offset ' . $offset;
            }
        }
        return $limit_clause;
    }

    // Получение количества объектов
    public function getCount($where = array()) {
        list($filter_clause, $filter_binds) = $this->getFilterCondition($where);
        return Db::selectCell("select count(*) from {$this->object} {$filter_clause}", $filter_binds);
    }

    // Получение списка объектов
    public function getList($where = array(), $order = array(), $limit = null, $offset = null) {
        list($filter_clause, $filter_binds) = $this->getFilterCondition($where);
        $order_clause = $this->getOrderClause($order);
        $limit_clause = $this->getLimitClause($limit, $offset);
        
        $records = Db::selectAll("select * from {$this->object} {$filter_clause} {$order_clause} {$limit_clause}", $filter_binds);
        
        return $this->getBatch($records);
    }

    // Сохранение объекта в БД
    public function save() {
        $record = array();
        foreach($this->fields_desc as $field_name => $field_desc) {
           if (!($this->is_new && $field_desc['type'] == 'pk')) {
                $errors = isset($field_desc['errors']) && is_array($field_desc['errors']) ? $field_desc['errors'] : array();
                if (!$this->fields[$field_name]->check($errors)) {
                    throw new \AlarmException('Ошибочное значение поля "' . $field_desc['title'] . '".');
                }
                
                $get_method = 'get' . to_field_name($field_name);
                $record[$field_name] = $this->$get_method();
            }
        }
        
        if ($this->is_new) {
            Db::insert($this->object, $record); $this->get(Db::lastInsertId());
        } else {
            Db::update($this->object, $record, array($this->primary_field => $this->getId()));
        }
        return $this;
    }

    // Удаление объекта из БД
    public function delete() {
        if ($this->is_new) {
            throw new \AlarmException("Ошибка. Запись не можеть быть удалена из БД, так как не имеет идентификатора.");
        }
        Db::delete($this->object, array($this->primary_field => $this->getId()));
        self::purge($this->object, $this->primary_field);
    }

    // Получение идентификатора объекта
    public function getId() {
        if ($this->is_new) {
            throw new \AlarmException("Ошибка. Запись не была сохранена в БД, поэтому не имеет идентификатора.");
        }
        return $this->fields[$this->primary_field]->get();
    }

    // Получение поля с идентификатором первичного ключа
    public function getPrimaryField() {
        return $this->primary_field;
    }

    // Очистка кеша объектов
    public static function purge($object = null, $primary_field = null) {
        if (!is_null($object) && !is_null($primary_field)) {
            unset(self::$object_cache[$object][$primary_field]);
        } elseif (!is_null($object)) {
            unset(self::$object_cache[$object]);
        } else {
            self::$object_cache = array();
        }
    }
}