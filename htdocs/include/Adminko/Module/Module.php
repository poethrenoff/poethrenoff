<?php
namespace Adminko\Module;

use Adminko\System;
use Adminko\View;

abstract class Module extends \Adminko\Object
{
    // Параметры модуля
    protected $params = array();

    // Вызываемый метод
    protected $action = null;

    // Модуль в главной области
    protected $is_main = false;

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // Создание объекта модуля
    public static final function factory($object)
    {
        $class_namespace = 'Module';
        $class_name = __NAMESPACE__ . '\\' . to_class_name($object) . $class_namespace;

        if (!class_exists($class_name)) {
            throw new \Exception('Ошибка. Класс "' . $class_name . '" не найден.');
        }

        return new $class_name($object);
    }

    // Инициализация модуля
    public function init($action = 'index', $params = array(), $is_main = false)
    {
        $this->view = new View();

        foreach ($params as $param_name => $param_value) {
            $this->params[$param_name] = $param_value;
        }

        $this->action = $action;
        $this->is_main = $is_main;

        $action_name = 'action' . to_class_name($action);

        if (!method_exists($this, $action_name)) {
            if (!$is_main) {
                throw new \AlarmException('Ошибка. Метод "' . $action_name . '" не найден.');
            } else {
                System::notFound();
            }
        }

        $cache_key = $this->getCacheKey();

        if (!$cache_key || ($cache_values = cache::get($cache_key)) === false) {
            $this->$action_name();

            if ($cache_key) {
                cache::set($cache_key, array($this->content, $this->output));
            }
        } else {
            list($this->content, $this->output) = $cache_values;
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    // У модуля обязательно должно быть действие по умолчанию
    protected abstract function actionIndex();
    
    // Возврашает значение параметра по его имени 
    protected function getParam($varname, $vardef = '')
    {
        if (isset($this->params[$varname])) {
            return $this->params[$varname];
        } else {
            return $vardef;
        }
    }

    // Вычисляет хэш параметров модуля
    protected function getCacheKey()
    {
        if (!System::isCache()) {
            return false;
        }

        if (!empty($_POST)) {
            return false;
        }

        $cache_key['_host'] = $_SERVER['HTTP_HOST'];
        $cache_key['_class'] = get_class($this);
        $cache_key['_action'] = $this->action;

        $cache_key += $this->params;

        parse_str($_SERVER['QUERY_STRING'], $query_string);
        $cache_key += $query_string;

        $cache_key += $this->extCacheKey();

        $cache_key = serialize($cache_key);

        return md5($cache_key);
    }

    // Дополнительные параметры хэша модуля
    protected function extCacheKey()
    {
        return array();
    }
}
