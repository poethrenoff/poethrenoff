<?php
namespace Adminko\Db;

use Adminko\Db\Driver\Driver;

abstract class Db
{
    protected static $db_driver = null;

    protected static function getDriver()
    {
        if (self::$db_driver == null) {
            self::$db_driver = Driver::factory(DB_TYPE, DB_HOST, '', DB_NAME, DB_USER, DB_PASSWORD);
        }

        return self::$db_driver;
    }

    protected static function getResult($method, $query, $fields = array(), $expiration = 0)
    {
        $cache_key = $expiration > 0 ? static::getCacheKey($query, $fields) : false;

        if (!$cache_key || ($result = cache::get($cache_key, $expiration)) === false) {
            $result = static::getDriver()->$method($query, $fields);

            if ($cache_key) {
                cache::set($cache_key, $result, $expiration);
            }
        }

        return $result;
    }

    protected static function getCacheKey($query, $fields = array())
    {
        return md5(serialize(array($query, $fields)));
    }

    ////////////////////////////////////////////////////////////////////////////////////////////

    public static function query($query, $fields = array())
    {
        return static::getDriver()->query($query, $fields);
    }

    public static function selectCell($query, $fields = array(), $expiration = 0)
    {
        return static::getResult('selectCell', $query, $fields, $expiration);
    }

    public static function selectRow($query, $fields = array(), $expiration = 0)
    {
        return static::getResult('selectRow', $query, $fields, $expiration);
    }

    public static function selectAll($query, $fields = array(), $expiration = 0)
    {
        return static::getResult('selectAll', $query, $fields, $expiration);
    }

    public static function insert($table, $fields = array())
    {
        return static::getDriver()->insert($table, $fields);
    }

    public static function update($table, $fields = array(), $where = array())
    {
        return static::getDriver()->update($table, $fields, $where);
    }

    public static function delete($table, $where = array())
    {
        return static::getDriver()->delete($table, $where);
    }

    public static function lastInsertId($sequence = null)
    {
        return static::getDriver()->lastInsertId($sequence);
    }

    public static function beginTransaction()
    {
        return static::getDriver()->beginTransaction();
    }

    public static function commit()
    {
        return static::getDriver()->commit();
    }

    public static function rollBack()
    {
        return static::getDriver()->rollBack();
    }

    public static function create()
    {
        return static::getDriver()->create();
    }
}
