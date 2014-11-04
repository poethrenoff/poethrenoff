<?php
namespace Adminko\Cache;

class Memory
{
    const CACHE_HOST = 'localhost';
    const CACHE_PORT = '11211';

    private static $cache_obj = null;

    private function getConnect()
    {
        if (!is_null(self::$cache_obj)) {
            return self::$cache_obj;
        }

        if (!class_exists('Memcache', false)) {
            return self::$cache_obj = false;
        }

        $cache_host = get_preference('CACHE_HOST', self::CACHE_HOST);
        $cache_port = get_preference('CACHE_PORT', self::CACHE_PORT);

        $cache_obj = new Memcache();
        if (@$cache_obj->pconnect($cache_host, $cache_port)) {
            return self::$cache_obj = $cache_obj;
        }

        return self::$cache_obj = false;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////

    public function get($key_name, $expire)
    {
        if ($cache_obj = self::get_connect()) {
            return @$cache_obj->get($key_name);
        }

        return false;
    }

    public function set($key_name, $var, $expire)
    {
        if ($cache_obj = self::getConnect()) {
            return @$cache_obj->set($key_name, $var, false, $expire);
        }

        return false;
    }

    public function clear()
    {
        if ($cache_obj = self::getConnect()) {
            return @$cache_obj->flush();
        }

        return false;
    }
}
