<?php
namespace Adminko\Cache;

abstract class Cache
{
    private static $cache_driver = null;
    
    private static function getDriver()
    {
        if (self::$cache_driver == null) {
            self::$cache_driver = self::factory();
        }

        return self::$cache_driver;
    }
    
    private static function factory($cache_type = CACHE_TYPE)
    {
        $class_namespace = 'Cache';
        $class_name = __NAMESPACE__ . '\\' . to_class_name($cache_type) . $class_namespace;
        
        if (!class_exists($class_name)) {
            throw new \Exception('Ошибка. Класс "' . $class_name . '" не найден.');
        }
        
        return new $class_name($cache_type);
    }
    
    private static function getKeyName($key)
    {
        return CACHE_DIR . md5($key);
    }
    
    ////////////////////////////////////////////////////////////////////////////////////////////
    
    public static function get($key, $expire = CACHE_TIME)
    {
        return self::getDriver()->get(self::getKeyName($key), $expire);
    }
    
    public static function set($key, $var, $expire = CACHE_TIME)
    {
        return self::getDriver()->set(self::getKeyName($key), $var, $expire);
    }
    
    public static function clear()
    {
        return self::getDriver()->clear();
    }
}
