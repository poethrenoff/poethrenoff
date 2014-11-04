<?php
namespace Adminko;

/**
 * Шаблонизатор
 */
class View
{
    // Каталог с шаблонами
    protected $template_dir = VIEW_DIR;

    // Объект, переданный в шаблон
    protected $object = null;

    /**
     * Передача переменной в шаблон
     *
     * @param  $name mix
     * @param  $data mix
     */
    public function assign($name, $data = null)
    {
        // Если переменая - объект
        if (is_object($name)) {
            $this->object = $name;
        // Если переменая - массив
        } elseif (is_array($name)) {
            foreach ($name as $key => $val) {
                $this->$key = $val;
            }
        // Если переменая - строка
        } elseif (is_string($name)) {
            $this->$name = $data;
        } else {
            throw new \AlarmException('Недопустимый тип переменной');
        }
        return $this;
    }

    /**
     * Вызов метода внутреннего объекта
     *
     * @param  $method string
     * @param  $vars   array
     * @return mixed
     */
    public function __call($method, $vars)
    {
        return call_user_func_array(array($this->object, $method), $vars);
    }

    /**
     * Получение контента
     *
     * @param  $template string
     * @return string
     */
    public function fetch($template)
    {
        $template_file = $this->template_dir . '/' . $template . '.phtml';

        if (!file_exists($template_file)) {
            throw new \AlarmException('Файл "' . normalize_path($template_file) . '" не найден');
        }

        $old_error_level = error_reporting();
        error_reporting(error_reporting() & ~(E_NOTICE | E_WARNING));

        ob_start();

        include($this->template_dir . '/' . $template . '.phtml');

        $result = ob_get_contents();

        ob_end_clean();

        error_reporting($old_error_level);

        return $result;
    }

    /**
     * Печать контента
     *
     * @param  $template string
     */
    public function display($template)
    {
        print $this->fetch($template);
    }

    /**
     * Алиас для htmlspecialchars + возможность обработки массивов
     *
     * @param  mixed $var
     */
    public function escape($var)
    {
        if (is_array($var)) {
            foreach ($var as $key => $value) {
                $var[$key] = $this->escape($value);
            }
        } else {
            $var = htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
        }
        return $var;
    }

    /**
     * Проверка присутствия данных в запросе
     *
     * Примеры вызова:
     *      $this->in_request('var', 1);
     *      $this->in_request('var[step]', 2);
     *      $this->in_request('var[step][point]');
     *
     * @param  $name string
     * @param  $data string
     * @return bool
     */
    public function inRequest($name, $data = null)
    {
        $isset = !is_null($value = $this->fromRequest($name));

        return (is_null($data) || !$isset) ? $isset : ($value == $data);
    }

    /**
     * Извлечение данных из запроса
     *
     * Примеры вызова:
     *      $this->from_request('var');
     *      $this->from_request('var[step]');
     *      $this->from_request('var[step][point]');
     *
     * @param  $name string
     * @param  $data string
     * @return bool
     */
    public function fromRequest($name, $data = null)
    {
        $path = array();
        if (preg_match('/^(.+)\[(.+)\]$/U', $name, $match)) {
            $name = $match[1];
            $path = explode('][', $match[2]);
        }

        if ($isset = isset($_REQUEST[$name])) {
            $value = $_REQUEST[$name];
            foreach ($path as $piece) {
                if (isset($value[$piece])) {
                    $value = $value[$piece];
                } else {
                    $isset = false;
                    break;
                }
            }
        }

        return $isset ? $value : $data;
    }

    /**
     * Получение статического блока
     *
     * @param  $template string
     * @param  $params   array
     * @return string
     */
    public static function block($template, $params = array())
    {
        $view = new View();

        $view->assign($params);

        return $view->fetch($template);
    }
}
