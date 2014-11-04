<?php
namespace Adminko;

class Session
{
    private static $storage = array();

    public static function start()
    {
        if (!session_id()) {
            session_start();
        }

        if (isset($_SESSION['__flash__'])) {
            self::$storage = $_SESSION['__flash__'];
            unset($_SESSION['__flash__']);
        }
    }

    public static function flash($name, $value = null)
    {
        if (is_null($value)) {
            $value = isset(self::$storage[$name]) ? self::$storage[$name] : null;
        } else {
            $_SESSION['__flash__'][$name] = $value;
        }

        return $value;
    }
}
