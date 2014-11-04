<?php
namespace Adminko\Valid;

abstract class Valid
{
    // Кеш объектов
    private static $object_cache = array();
    
    abstract public function check($content);
    
    // Создание объекта валидатора
    public static final function factory($type)
    {
        if (!isset(self::$object_cache[$type])) {
            $class_namespace = 'Valid';
            $class_name = __NAMESPACE__ . '\\' . to_class_name($type) . $class_namespace;

            if (!class_exists($class_name)) {
                throw new \Exception('Ошибка. Класс "' . $class_name . '" не найден.');
            }
            
            self::$object_cache[$type] = new $class_name();
        }
        return self::$object_cache[$type];
    }
}