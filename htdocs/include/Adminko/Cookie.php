<?php
namespace Adminko;

class Cookie
{
    // Записывает массив (или произвольный объект) в куки.
    static function setData($name, $value = "", $expire = 0, $path = "", $domain = "", $secure = "")
    {
        $value = @base64_encode(gzcompress(serialize($value), 9));
        return setcookie($name, $value, $expire, $path, $domain, $secure);
    }

    // Возвращает массив (или произвольный объект) из кук.
    static function getData($name)
    {
        if (!isset($_COOKIE[$name])) {
            return false;
        }
        $data = @base64_decode($_COOKIE[$name]);
        if ($data === false) {
            return false;
        }
        $data = @gzuncompress($data);
        if ($data === false) {
            return false;
        }
        $data = @unserialize($data);
        if ($data === false) {
            return false;
        }
        return $data;
    }
}
