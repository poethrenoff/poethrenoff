<?php
namespace Adminko\Cache;

class FileCache
{
    public function get($file_name, $expire)
    {
        if (!file_exists($file_name)) {
            return false;
        }

        if (filemtime($file_name) + $expire < time()) {
            return false;
        }

        $var = @file_get_contents($file_name);

        return @unserialize($var);
    }

    public function set($file_name, $var, $expire)
    {
        @file_put_contents($file_name, serialize($var));

        return file_exists($file_name);
    }

    public function clear()
    {
        $file_list = array_diff(scandir(CACHE_DIR), array('.', '..'));

        foreach ($file_list as $file_name) {
            $file_path = CACHE_DIR . '/' . $file_name;
            if (is_file($file_path) && is_empty(pathinfo($file_path, PATHINFO_EXTENSION))) {
                @unlink($file_path);
            }
        }

        return true;
    }
}
