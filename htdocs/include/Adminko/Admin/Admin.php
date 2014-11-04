<?php
namespace Adminko\Admin;

use Adminko\System;
use Adminko\Session;
use Adminko\Metadata;
use Adminko\View;
use Adminko\Db\Db;

abstract class Admin extends \Adminko\Object
{
    // Метаданные объекта
    protected $object_desc = array();
    
    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    public function __construct($object)
    {
        parent::__construct($object);
        
        $this->object_desc = Metadata::$objects[$object];
        
        $this->output['meta_title'] = 'Admin&K° :: ' . SITE_TITLE . ' :: ' . $this->object_desc['title'];
    }
    
    public static final function factory($object, $access_exception = true)
    {
        if (isset($_REQUEST['logout'])) {
            self::logout();
        }

        if (!self::getAdmin()) {
            self::login();
        }

        $object_list = array_filter(self::getObjectList(), 'trim');
        if (!count($object_list)) {
            self::unauthorized();
        }

        $default_object = get_preference('default_object', 'text');
        if (is_empty($object)) {
            $object = in_array($default_object, $object_list) ?
                    $default_object : current($object_list);
        }

        if ($access_exception && !in_array($object, $object_list)) {
            throw new \Exception('Ошибка. У вас нет прав доступа к этому объекту.');
        }

        if (!self::$metadata_prepared) {
            self::prepareMetadata();
        }

        if (!isset(Metadata::$objects[$object])) {
            throw new \Exception('Ошибка. Объект не описан в метаданных.');
        }

        $object_desc = Metadata::$objects[$object];
        
        if (isset($object_desc['internal']) && $object_desc['internal']) {
            throw new \Exception('Ошибка. Попытка обратиться к внутреннему объекту "' . $object . '".');
        }
        
        if (isset($object_desc['fields']) && $object_desc['fields']) {
            $class_namespace = 'Table';
        } else {
            $class_namespace = 'Tool';
        }
                
        if (isset($object_desc['class']) && $object_desc['class']) {
            $class_name = $object_desc['class'];
        } else {
            $class_name = to_class_name($object);
        }
        
        $class_name = __NAMESPACE__ . '\\' . $class_namespace . '\\' . $class_name . $class_namespace;
        if (!class_exists($class_name)) {
            $class_name = __NAMESPACE__ . '\\' . $class_namespace . '\\' . $class_namespace;
        }
        
        return new $class_name($object);
    }
    
    public function init($action = 'index')
    {
        $this->view = new View();
        
        $action_name = 'action' . to_class_name($action);
        
        if (method_exists($this, $action_name)) {
            $this->$action_name();
        } else {
            throw new \AlarmException('Ошибка. Метод "' . $action_name . '" не найден.');
        }
    }
    
    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    protected abstract function actionIndex();
    
    protected function actionMenu()
    {
        $this->view->assign('object_tree', self::getObjectTree($this->object));
        
        $this->content = $this->view->fetch('admin/menu');
    }
    
    protected function actionAuth()
    {
        $this->view->assign('admin', self::getAdmin());
        
        $this->content = $this->view->fetch('admin/auth');
    }
    
    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    protected function storeState($obj_name = 'prev_url')
    {
        $_SESSION[$obj_name] = array_merge($_GET,
            array('object' => $this->object, 'action' => System::action()),
                System::id() ? array('id' => System::id()) : array());
    }
    
    protected function restoreState($obj_name = 'prev_url')
    {
        return isset($_SESSION[$obj_name]) && is_array($_SESSION[$obj_name]) ? $_SESSION[$obj_name] :
            array('object' => System::object(), 'action' => 'index');
    }
    
    protected function redirect($obj_name = 'prev_url')
    {
        System::redirectTo($this->restoreState($obj_name));
    }
    
    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    private static $admin = null;
    
    private static $object_list = array();
    
    public static function login()
    {
        if (Session::flash('logout'))             {
            self::unauthorized();
        }
        
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))             {
            self::$admin = Db::selectRow('
                select * from admin where admin_login = :admin_login and admin_password = :admin_password and admin_active = 1',                 array('admin_login' => $_SERVER['PHP_AUTH_USER'], 'admin_password' => md5($_SERVER['PHP_AUTH_PW'])));
        }
        
        if (!self::$admin) {
            self::unauthorized();
        }

        $role_list = Db::selectAll('
                select role.role_id, role.role_default
                from role, admin_role
                where admin_role.role_id = role.role_id and
                    admin_role.admin_id = :admin_id
                order by role.role_default desc',
            array('admin_id' => self::$admin['admin_id']));
        
        if (count($role_list))
        {
            if ($role_list[0]['role_default']) {
                $object_list = Db::selectAll('
                    select object_id, object_name from object');
            } else
                $object_list = Db::selectAll('
                    select object.object_id, object_name from object, role_object
                    where role_object.object_id = object.object_id and
                        role_id in (' . array_make_in($role_list, 'role_id') . ')');

            foreach ($object_list as $object_item) {
                self::$object_list[$object_item['object_id']] = $object_item['object_name'];
            }
        }
    }
    
    public static function getObjectTree($current_object)
    {
        $object_list = Db::selectAll('
            select * from object where object_active = 1 and
                object_id in (' . array_make_in(array_keys(self::$object_list)) . ')
            order by object_order');
        $object_tree = Admin::factory('object', false)->getTree($object_list);
        
        foreach ($object_tree as $object_index => $object_item)
        {
            if ($object_item['object_name'] == $current_object) {
                $object_tree[$object_index]['_selected'] = true;
            }
            if (!is_empty($object_item['object_name'])) {
                $object_tree[$object_index]['object_url'] = System::urlFor(array('controller' => System::controller(), 'object' => $object_item['object_name']));
            }
        }
        
        return $object_tree;
    }
    
    public static function logout()
    {
        Session::flash('logout', true);
        
        header('Location: /admin/');
        exit;
    }
    
    public static function unauthorized()
    {
        header('WWW-Authenticate: Basic realm="Administration interface"');
        header('HTTP/1.0 401 Unauthorized');
        
        $error_view = new view();
        $error_view->assign('message', 'Извините, у вас нет прав доступа к этой странице.');
        $error_content = $error_view->fetch('block/error');
        
        die($error_content);
    }
    
    public static function getAdmin()
    {
        return self::$admin;
    }
    
    public static function getObjectList()
    {
        return self::$object_list;
    }
    
    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    private static final function prepareMetadata()
    {
        foreach (Metadata::$objects as $object_name => $object_desc)
        {
            if (!(isset($object_desc['fields']) && $object_desc['fields'])) {
                continue;
            }

            if (isset($object_desc['links']) && is_array($object_desc['links'])) {
                foreach ($object_desc['links'] as $link_name => $link_desc) {
                    if (!isset($link_desc['table']) || !$link_desc['table'] ||
                            !isset(Metadata::$objects[$link_desc['table']]) || !Metadata::$objects[$link_desc['table']]) {
                        throw new \Exception('Ошибка в описании связи таблиц "' . $object_name . '.' . $link_name . '". Ошибка при задании целевой таблицы.');
                    }
                    if (!isset($link_desc['field']) || !$link_desc['field'] ||
                            !isset(Metadata::$objects[$link_desc['table']]['fields'][$link_desc['field']]['type']) ||
                            Metadata::$objects[$link_desc['table']]['fields'][$link_desc['field']]['type'] !== 'table' ||
                            !isset(Metadata::$objects[$link_desc['table']]['fields'][$link_desc['field']]['table']) ||
                            Metadata::$objects[$link_desc['table']]['fields'][$link_desc['field']]['table'] != $object_name) {
                        throw new \Exception('Ошибка в описании связи таблиц "' . $object_name . '.' . $link_name . '". Ошибка при задании целевого поля.');
                    }

                    if (isset($link_desc['show']) && is_array($link_desc['show'])) {
                        foreach ($link_desc['show'] as $show_field_name => $show_field_values) {
                            if (!isset(Metadata::$objects[$object_name]['fields'][$show_field_name])) {
                                throw new \Exception('Ошибка в описании связи таблиц "' . $object_name . '.' . $link_name . '". Ошибка при задании целевого поля в опции "show".');
                            }
                        }
                    }

                    if (isset(Metadata::$objects[$link_desc['table']]['internal']) && Metadata::$objects[$link_desc['table']]['internal']) {
                        Metadata::$objects[$object_name]['links'][$link_name]['hidden'] = 1;
                    }

                    if (!isset($link_desc['hidden']) || !$link_desc['hidden']) {
                        Metadata::$objects[$link_desc['table']]['fields'][$link_desc['field']]['filter'] = 1;
                    }
                }
            }
            
            if (isset($object_desc['relations']) && is_array($object_desc['relations'])) {
                foreach ($object_desc['relations'] as $relation_name => $relation_desc) {
                    if (!isset($relation_desc['secondary_table']) || !$relation_desc['secondary_table'] ||
                            !isset(Metadata::$objects[$relation_desc['secondary_table']]) || !Metadata::$objects[$relation_desc['secondary_table']]) {
                        throw new \Exception('Ошибка в описании отношения таблиц "' . $object_name . '.' . $relation_name . '". Ошибка при задании вторичной таблицы.');
                    }
                    if (!isset($relation_desc['relation_table']) || !$relation_desc['relation_table'] ||
                            !isset(Metadata::$objects[$relation_desc['relation_table']]) || !Metadata::$objects[$relation_desc['relation_table']]) {
                        throw new \Exception('Ошибка в описании отношения таблиц "' . $object_name . '.' . $relation_name . '". Ошибка при задании связующей таблицы.');
                    }

                    if (!isset($relation_desc['primary_field']) || !$relation_desc['primary_field'] ||
                            !isset(Metadata::$objects[$relation_desc['relation_table']]['fields'][$relation_desc['primary_field']]['type']) ||
                            Metadata::$objects[$relation_desc['relation_table']]['fields'][$relation_desc['primary_field']]['type'] !== 'table' ||
                            !isset(Metadata::$objects[$relation_desc['relation_table']]['fields'][$relation_desc['primary_field']]['table']) ||
                            Metadata::$objects[$relation_desc['relation_table']]['fields'][$relation_desc['primary_field']]['table'] != $object_name) {
                        throw new \Exception('Ошибка в описании отношения таблиц "' . $object_name . '.' . $relation_name . '". Ошибка при задании первичного поля связующей таблицы.');
                    }
                    if (!isset($relation_desc['secondary_field']) || !$relation_desc['secondary_field'] ||
                            !isset(Metadata::$objects[$relation_desc['relation_table']]['fields'][$relation_desc['secondary_field']]['type']) ||
                            Metadata::$objects[$relation_desc['relation_table']]['fields'][$relation_desc['secondary_field']]['type'] !== 'table' ||
                            !isset(Metadata::$objects[$relation_desc['relation_table']]['fields'][$relation_desc['secondary_field']]['table']) ||
                            Metadata::$objects[$relation_desc['relation_table']]['fields'][$relation_desc['secondary_field']]['table'] != $relation_desc['secondary_table']) {
                        throw new \Exception('Ошибка в описании отношения таблиц "' . $object_name . '.' . $relation_name . '". Ошибка при задании вторичного поля связующей таблицы.');
                    }

                    Metadata::$objects[$relation_desc['secondary_table']]['links'][$relation_desc['relation_table']] = array('table' => $relation_desc['relation_table'], 'field' => $relation_desc['secondary_field'],
                        'hidden' => 1, 'ondelete' => 'cascade');
                }
            }
        }
        
        foreach (Metadata::$objects as $object_name => $object_desc)
        {
            if (!(isset($object_desc['fields']) && $object_desc['fields'])) {
                continue;
            }

            if (isset($object_desc['internal']) && $object_desc['internal'])
            {
                if (isset($object_desc['fields'])) {
                    foreach ($object_desc['fields'] as $field_name => $field_desc) {
                        if (!isset($field_desc['type']) || !$field_desc['type']) {
                            throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Не задан тип поля.');
                        }

                        if ($field_desc['type'] == 'order' || $field_desc['type'] == 'active' ||
                                $field_desc['type'] == 'default' || $field_desc['type'] == 'parent') {
                            throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Недопустимый тип поля.');
                        }

                        if (isset($field_desc['group'])) {
                            throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Скрытые таблицы не поддерживают условий группировки.');
                        }

                        if ($field_desc['type'] == 'table' &&
                                (!(isset($field_desc['table']) && isset(Metadata::$objects[$field_desc['table']])))) {
                            throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Ошибка при задании целевой таблицы.');
                        }
                        if ($field_desc['type'] == 'select' &&
                                (!isset($field_desc['values']) || !(is_array($field_desc['values']) && count($field_desc['values']) ||
                                !is_array($field_desc['values']) && $field_desc['values'] == '__OBJECT__'))) {
                            throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Ошибка при задании списка значений поля типа "select".');
                        }

                        $errors = isset($field_desc['errors']) && is_array($field_desc['errors']) ? $field_desc['errors'] : array();
                        Metadata::$objects[$object_name]['fields'][$field_name]['errors'] = $errors;
                    }
                }

                continue;
            }
            
            foreach ($object_desc['fields'] as $field_name => $field_desc)
            {
                if (!isset($field_desc['type']) || !$field_desc['type']) {
                    throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Не задан тип поля.');
                }

                if ($field_desc['type'] == 'pk') {
                    Metadata::$objects[$object_name]['primary_field'] = $field_name;
                }
                if ($field_desc['type'] == 'parent') {
                    Metadata::$objects[$object_name]['parent_field'] = $field_name;
                }
                if ($field_desc['type'] == 'active') {
                    Metadata::$objects[$object_name]['active_field'] = $field_name;
                }
                if ($field_desc['type'] == 'order') {
                    Metadata::$objects[$object_name]['order_field'] = $field_name;
                }
                if (isset($field_desc['main']) && $field_desc['main'] &&
                        $field_desc['type'] != 'pk' && $field_desc['type'] != 'parent' &&
                        $field_desc['type'] != 'active' && $field_desc['type'] != 'order' &&
                        $field_desc['type'] != 'default') {
                    Metadata::$objects[$object_name]['main_field'] = $field_name;
                }

                if ((isset($field_desc['show']) && $field_desc['show'] ||
                        isset($field_desc['main']) && $field_desc['main']) &&
                        $field_desc['type'] != 'pk' && $field_desc['type'] != 'parent' &&
                        $field_desc['type'] != 'active' && $field_desc['type'] != 'order') {
                    Metadata::$objects[$object_name]['show_fields'][] = $field_name;
                }
                if ((isset($field_desc['filter']) && $field_desc['filter'] ||
                        isset($field_desc['main']) && $field_desc['main'] || $field_desc['type'] == 'active') &&
                        $field_desc['type'] != 'pk' && $field_desc['type'] != 'parent' &&
                        $field_desc['type'] != 'image' && $field_desc['type'] != 'file' &&
                        $field_desc['type'] != 'order' && $field_desc['type'] != 'default' &&
                        $field_desc['type'] != 'date' && $field_desc['type'] != 'datetime')
                {
                    Metadata::$objects[$object_name]['filter_fields'][] = $field_name;
                    if ($field_desc['type'] == 'table') {
                        Metadata::$objects[$object_name]['fields'][$field_name]['search'] = isset(Metadata::$objects[$object_name]['fields'][$field_name]['search']) &&
                                Metadata::$objects[$object_name]['fields'][$field_name]['search'] == 'text' ? 'text' : 'table';
                    }
                }
                if (isset($field_desc['sort']) &&
                        (isset($field_desc['show']) && $field_desc['show'] || isset($field_desc['main']) &&
                        $field_desc['main']) && $field_desc['type'] != 'pk' && $field_desc['type'] != 'parent' &&
                        $field_desc['type'] != 'active' && $field_desc['type'] != 'default')
                {
                    Metadata::$objects[$object_name]['sort_field'] = $field_name;
                    Metadata::$objects[$object_name]['sort_order'] = $field_desc['sort'] == 'desc' ? 'desc' : 'asc'; 
                }
                
                if ($field_desc['type'] == 'table' &&
                        (!(isset($field_desc['table']) && isset(Metadata::$objects[$field_desc['table']])) ||
                        (isset(Metadata::$objects[$field_desc['table']]['internal']) && Metadata::$objects[$field_desc['table']]['internal']))) {
                    throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Ошибка при задании целевой таблицы.');
                }
                if ($field_desc['type'] == 'select' &&
                        (!isset($field_desc['values']) || !(is_array($field_desc['values']) && count($field_desc['values']) ||
                        !is_array($field_desc['values']) && $field_desc['values'] == '__OBJECT__'))) {
                    throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Ошибка при задании списка значений поля типа "select".');
                }

                if (isset($field_desc['group']))
                {
                    if (!is_array($field_desc['group'])) {
                        throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Ошибка при задании целевого поля в опции "group".');
                    }
                    foreach ($field_desc['group'] as $group_field_name) {
                        if ($group_field_name == $field_name ||
                                !isset($object_desc['fields'][$group_field_name]) ||
                                $object_desc['fields'][$group_field_name]['type'] == 'pk' ||
                                $object_desc['fields'][$group_field_name]['type'] == 'active' ||
                                $object_desc['fields'][$group_field_name]['type'] == 'order' ||
                                $object_desc['fields'][$group_field_name]['type'] == 'default') {
                            throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Ошибка при задании целевого поля в опции "group".');
                        }
                    }
                }
                
                if (($field_desc['type'] == 'image' || $field_desc['type'] == 'file') &&
                        !(isset($field_desc['upload_dir']) && $field_desc['upload_dir'])) {
                    throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Не задан каталог для закачки файлов.');
                }

                $errors = isset($field_desc['errors']) && is_array($field_desc['errors']) ? $field_desc['errors'] : array();
                Metadata::$objects[$object_name]['fields'][$field_name]['errors'] = $errors;
                
                if ($field_desc['type'] == 'table')
                {
                    $link_show = false;
                    if (isset(Metadata::$objects[$field_desc['table']]['links']) &&
                            is_array(Metadata::$objects[$field_desc['table']]['links'])) {
                        foreach (Metadata::$objects[$field_desc['table']]['links'] as $link_name => $link_desc) {
                            if ($link_desc['table'] == $object_name) {
                                $link_show = true;
                            }
                        }
                    }

                    if (!$link_show) {
                        Metadata::$objects[$field_desc['table']]['links'][$object_name] = array('table' => $object_name, 'field' => $field_name, 'hidden' => 1) +
                                (!in_array('require', $errors) ? array('ondelete' => 'set_null') : array());
                    }
                }
                
                if (isset($field_desc['translate']) && $field_desc['translate'] &&
                        !($field_desc['type'] == 'string' || $field_desc['type'] == 'text')) {
                    throw new \Exception('Ошибка в описании поля "' . $object_name . '.' . $field_name . '". Переводимыми могут быть только поля типа string и text.');
                }
            }
            
            if (!(isset(Metadata::$objects[$object_name]['primary_field']) && Metadata::$objects[$object_name]['primary_field'])) {
                throw new \Exception('Ошибка в описании таблицы "' . $object_name . '". Отсутствует ключевое поле.');
            }
            if (!(isset(Metadata::$objects[$object_name]['main_field']) && Metadata::$objects[$object_name]['main_field'])) {
                throw new \Exception('Ошибка в описании таблицы "' . $object_name . '". Отсутствует главное поле.');
            }

            if (isset(Metadata::$objects[$object_name]['order_field']) && Metadata::$objects[$object_name]['order_field'])
            {
                Metadata::$objects[$object_name]['sort_field'] = Metadata::$objects[$object_name]['order_field'];
                Metadata::$objects[$object_name]['sort_order'] = 'asc'; 
            }
            else if (!(isset(Metadata::$objects[$object_name]['sort_field']) && Metadata::$objects[$object_name]['sort_field']) &&
                isset(Metadata::$objects[$object_name]['main_field']) && Metadata::$objects[$object_name]['main_field'])
            {
                Metadata::$objects[$object_name]['sort_field'] = Metadata::$objects[$object_name]['main_field'];
                Metadata::$objects[$object_name]['sort_order'] = 'asc'; 
            }
        }
        
        $metadata_object_list = array(); 
        foreach (Metadata::$objects as $object_name => $object_desc) {
            if (!(isset($object_desc['internal']) && $object_desc['internal'])) {
                $metadata_object_list[$object_name] = $object_desc['title'];
            }
        }
        asort($metadata_object_list);
        
        $metadata_values = array();
        foreach ($metadata_object_list as $object_name => $object_title) {
            $metadata_values[] = array('value' => $object_name, 'title' => $object_title);
        }

        foreach (Metadata::$objects as $object_name => $object_desc) {
            if (isset($object_desc['fields']) && $object_desc['fields']) {
                foreach ($object_desc['fields'] as $field_name => $field_desc) {
                    if ($field_desc['type'] == 'select' && $field_desc['values'] == '__OBJECT__') {
                        Metadata::$objects[$object_name]['fields'][$field_name]['values'] = $metadata_values;
                    }
                }
            }
        }

        self::$metadata_prepared = true;
    }
    
    private static $metadata_prepared = false;
}
