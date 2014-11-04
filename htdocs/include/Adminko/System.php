<?php
namespace Adminko;

use Adminko\Db\Db;
use Adminko\Cache\Cache;
use Adminko\Admin\Admin;
use Adminko\Module\Module;

class System
{
    private static $routes = null;

    private static $params = null;

    private static $cache_mode = true;

    private static $lang = null;

    private static $lang_list = null;

    private static $site = null;

    private static $page = null;

    private static $key_name = '__SITE__';

    public static function init()
    {
        self::$site = Cache::get(self::$key_name);
        if (self::$site === false) {
            self::$site = self::build();
        }

        if (!self::$site) {
            exit;
        }

        if (isset(self::$site['lang'])) {
            foreach (self::$site['lang'] as $lang) {
                self::$lang_list[$lang['lang_name']] = $lang['lang_id'];

                if ($lang['lang_default']) {
                    self::$lang = $lang['lang_name'];

                    if (isset($lang['dictionary'])) {
                        foreach ($lang['dictionary'] as $word_name => $word_value) {
                            if (!defined($word_name)) {
                                define($word_name, $word_value, true);
                            }
                        }
                    }
                }
            }
        }

        if (isset(self::$site['preference'])) {
            foreach (self::$site['preference'] as $preference_name => $preference_value) {
                if (!defined($preference_name)) {
                    define($preference_name, $preference_value, true);
                }
            }
        }
    }

    public static function dispatcher()
    {
        Session::start();

        set_exception_handler('Adminko\System::exceptionHandler');

        $routes = self::getRoutes();

        self::$page = null;
        $url = '/' . trim(self::selfUrl(), '/');

        $page_list = array_reindex(self::$site['page'], 'page_path');

        foreach ($routes as $route_rule => $route_item) {
            if (preg_match($route_item['pattern'], $url, $route_match)) {
                $params = array();

                foreach ($route_item['params'] as $route_name => $route_item) {
                    if (preg_match('|^#(\d+)$|', $route_item, $item_match)) {
                        $params[$route_name] = trim($route_match[$item_match[1]], '/');
                    } else {
                        $params[$route_name] = trim($route_item, '/');
                    }
                }

                if (isset($params['controller']) && isset($page_list['/' . $params['controller']])) {
                    self::$params = $params;
                    self::$page = $page_list['/' . $params['controller']];

                    break;
                }
            }
        }

        if (is_null(self::controller())) {
            self::notFound();
        }

        if (isset(self::$page['page_redirect'])) {
            if (self::action() == 'index') {
                self::redirectTo(self::$page['page_redirect']);
            } else {
                self::notFound();
            }
        }

        self::setCacheMode();

        if (isset(self::$site['lang'])) {
            if (preg_match('/^(\w+)\/?/', self::controller(), $match) &&
                    in_array($match[1], array_keys(self::$lang_list))) {
                self::$lang = $match[1];
            }
        }

        $layout_view = new View();

        $layout_view->assign(array(
            'meta_title' => self::$page['meta_title'],
            'meta_keywords' => self::$page['meta_keywords'],
            'meta_description' => self::$page['meta_description']));

        $is_admin = self::controller() == 'admin';

        if (isset(self::$page['block'])) {
            foreach (self::$page['block'] as $block) {
                $module_params = array();
                if (isset($block['param'])) {
                    $module_params = $block['param'];
                }

                $module_name = $block['module_name'];
                $module_main = (boolean) $block['area_main'];
                $module_action = $module_main ? self::action() :
                        ((isset($module_params['action']) && $module_params['action']) ? $module_params['action'] : 'index');

                try {
                    if ($is_admin) {
                        $module_object = Admin::factory(System::object());
                    } else {
                        $module_object = Module::factory($module_name);
                    }

                    $module_object->init($module_action, $module_params, $module_main);

                    if ($module_main && self::isAjax()) {
                        die($module_object->getContent());
                    }

                    $layout_view->assign($block['area_name'], $module_object->getContent());
                    if ($module_main) {
                        $layout_view->assign($module_object->getOutput());
                    }
                } catch (\AlarmException $e) {
                    $error_content = self::exceptionHandler($e, true, $is_admin);

                    $layout_view->assign($block['area_name'], $error_content);
                } catch (\Exception $e) {
                    self::exceptionHandler($e, false, $is_admin);
                }
            }
        }

        $layout_view->display(self::$page['page_layout']);
    }

    public static function getRoutes()
    {
        if (!is_null(self::$routes)) {
            return self::$routes;
        }

        include_once APP_DIR . '/config/routes.php';

        $routes = array_merge($routes, array(
            '/admin/@object' => array(
                'controller' => 'admin',
                'object' => '\w+',
            ),
            '/admin/@object/@action' => array(
                'controller' => 'admin',
                'object' => '\w+',
            ),
            '/admin/@object/@action/@id' => array(
                'controller' => 'admin',
                'object' => '\w+',
            ),
            '@controller' => array(),
            '@controller/@id' => array(),
            '@controller/@action' => array(),
            '@controller/@action/@id' => array()));

        foreach ($routes as $route_rule => $route_rule_params) {
            $route_pattern = $route_rule;
            $route_pattern_params = $route_rule_params;

            if (preg_match_all('/@\w+/i', $route_rule, $route_match)) {
                foreach ($route_match[0] as $route_index => $route_name) {
                    $route_index_name = '#' . ($route_index + 1);

                    if ($route_name == '@controller') {
                        $route_pattern = preg_replace('/@controller/', '([/\w]*)', $route_pattern);
                        $route_pattern_params['controller'] = $route_index_name;
                    } else if ($route_name == '@action') {
                        $route_pattern = preg_replace('/@action/', '(\w*)', $route_pattern);
                        $route_pattern_params['action'] = $route_index_name;
                    } else if ($route_name == '@id') {
                        $route_pattern = preg_replace('/@id/', '(\d*)', $route_pattern);
                        $route_pattern_params['id'] = $route_index_name;
                    } else {
                        $route_var_name = preg_replace('/@/', '', $route_name);
                        $route_var_value = isset($route_rule_params[$route_var_name]) ?
                                $route_rule_params[$route_var_name] : '[^\/]*';

                        $route_pattern = preg_replace('/' . $route_name . '/', '(' . $route_var_value . ')', $route_pattern);
                        $route_pattern_params[$route_var_name] = $route_index_name;
                    }
                }
            }

            if (!isset($route_pattern_params['controller'])) {
                $route_pattern_params['controller'] = '';
            }
            if (!isset($route_pattern_params['action'])) {
                $route_pattern_params['action'] = 'index';
            }

            $route_pattern = '|^' . $route_pattern . '$|i';

            self::$routes[$route_rule] = array('pattern' => $route_pattern, 'params' => $route_pattern_params);
        }

        return self::$routes;
    }

    public static function urlFor($url_array = array(), $url_host = '')
    {
        if (!is_array($url_array) || count($url_array) == 0) {
            return self::selfUrl();
        }

        if (!isset($url_array['action'])) {
            $url_array['action'] = !isset($url_array['controller']) ? self::action() : 'index';
        }
        if (!isset($url_array['controller'])) {
            $url_array['controller'] = self::controller();
        }

        $routes = self::getRoutes();

        $most_match_rule = '';
        $most_match_count = 0;
        foreach ($routes as $route_rule => $route_item) {
            if (count(array_diff_key($route_item['params'], $url_array)) == 0) {
                $is_match = true;
                foreach ($route_item['params'] as $route_param_name => $route_param_value) {
                    if (!preg_match('|^#(\d+)$|', $route_param_value)) {
                        $is_match &= $url_array[$route_param_name] === $route_param_value;
                    }
                }

                if ($is_match) {
                    $match_count = count(array_intersect_key($route_item['params'], $url_array));
                    if ($match_count > $most_match_count) {
                        $most_match_count = $match_count;
                        $most_match_rule = $route_rule;
                    }
                }
            }
        }

        $url = $most_match_rule;
        if ($url_array['action'] == 'index') {
            $url = preg_replace('|/@action$|i', '', $url);
        }

        foreach ($routes[$most_match_rule]['params'] as $route_param_name => $route_param_value) {
            $url = preg_replace('/@' . $route_param_name . '/', $url_array[$route_param_name], $url);
        }

        $query_string = http_build_query(self::prepareQuery($url_array, array_keys($routes[$most_match_rule]['params'])));

        $url = $url_host . '/' . trim($url, '/') . ($query_string ? '?' . $query_string : '');

        return $url;
    }
    
    public static function prepareQuery($include = array(), $exclude = array())
    {
        foreach ($include as $var_name => $var_value) {
            if (in_array($var_name, $exclude) || is_empty($var_value)) {
                unset($include[$var_name]);
            }
        }

        return $include;
    }
    
    public static function selfUrl($include = array(), $exclude = array())
    {
        $self_url = preg_replace('/\?.*$/', '', filter_input(INPUT_SERVER, 'REQUEST_URI'));

        $query_string = http_build_query(self::prepareQuery($include, $exclude));

        return $self_url . ($query_string ? '?' . $query_string : '');
    }

    public static function requestUrl($include = array(), $exclude = array())
    {
        return self::selfUrl(array_merge($_GET, $include), $exclude);
    }
    
    public static function redirectTo($url_array = array())
    {
        if (!is_array($url_array)) {
            $location = $url_array;
        } else {
            $location = self::urlFor($url_array);
        }

        header('Location: ' . $location);

        exit;
    }
    
    public static function redirectBack()
    {
        $back_url = '/';
        
        $http_host = filter_input(INPUT_SERVER, 'HTTP_REFERER');
        $http_refferer = filter_input(INPUT_SERVER, 'HTTP_REFERER');

        if (!is_null($http_refferer) && strstr($http_refferer, $http_host)) {
            $back_url = $http_refferer;
        }

        self::redirectTo($back_url);
    }

    public static function build()
    {
        $page_list = Db::selectAll('select * from page, layout where page_layout = layout_id and page_active = 1 order by page_order');
        $page_list = array_reindex($page_list, 'page_id');

        $area_list = Db::selectAll('select * from layout_area order by area_order');
        $area_list = array_group($area_list, 'area_layout');

        $block_list = Db::selectAll('select * from block, module where block_module = module_id');
        $block_list = array_reindex($block_list, 'block_page', 'block_area');

        $block_param_list = Db::selectAll('select * from block_param, module_param where param = param_id');
        $block_param_list = array_group($block_param_list, 'block');

        $param_value_list = Db::selectAll('select * from param_value');
        $param_value_list = array_reindex($param_value_list, 'value_id');

        $site = array();

        foreach ($page_list as $page) {
            $site_page = array();

            $page_path = array($page['page_name']);
            $page_parent = $page['page_id'];
            while ($page_parent = $page_list[$page_parent]['page_parent']) {
                $page_path[] = $page_list[$page_parent]['page_name'];
            }

            $page_path = array_reverse($page_path);
            array_shift($page_path);

            $site_page['page_id'] = $page['page_id'];
            $site_page['page_path'] = '/' . join('/', $page_path);

            if ($page['page_folder']) {
                $page_redirect = Db::selectRow('select * from page where page_parent = :page_parent and page_active = 1 order by page_order', array('page_parent' => $page['page_id']));

                if ($page_redirect) {
                    $site_page['page_redirect'] = rtrim($site_page['page_path'], '/') . '/' . $page_redirect['page_name'];
                } else {
                    continue;
                }
            }
            else {
                $site_page['page_layout'] = $page['layout_name'];

                $site_page['meta_title'] = $page['meta_title'];
                $site_page['meta_keywords'] = $page['meta_keywords'];
                $site_page['meta_description'] = $page['meta_description'];

                if (isset($area_list[$page['layout_id']])) {
                    foreach ($area_list[$page['layout_id']] as $area) {
                        if (isset($block_list[$page['page_id']][$area['area_id']])) {
                            $block = $block_list[$page['page_id']][$area['area_id']];

                            $page_block = array();

                            $page_block['area_name'] = $area['area_name'];
                            $page_block['area_main'] = $area['area_main'] ? 1 : 0;
                            $page_block['module_name'] = $block['module_name'];

                            if (isset($block_param_list[$block['block_id']])) {
                                foreach ($block_param_list[$block['block_id']] as $param) {
                                    if ($param['param_type'] == 'select') {
                                        $page_block['param'][$param['param_name']] = isset($param_value_list[$param['value']]) ?
                                                $param_value_list[$param['value']]['value_content'] : '';
                                    } else {
                                        $page_block['param'][$param['param_name']] = $param['value'];
                                    }
                                }
                            }

                            $site_page['block'][] = $page_block;
                        }
                    }
                }
            }

            $site['page'][] = $site_page;
        }

        $site['page'][] = array('page_id' => 'admin', 'page_path' => '/admin', 'page_layout' => 'admin',
            'meta_title' => '', 'meta_keywords' => '', 'meta_description' => '', 'block' => array(
                array('area_name' => 'content', 'area_main' => 1, 'module_name' => 'admin', 'param' => array(
                        'action' => 'index')),
                array('area_name' => 'menu', 'area_main' => 0, 'module_name' => 'admin', 'param' => array(
                        'action' => 'menu')),
                array('area_name' => 'auth', 'area_main' => 0, 'module_name' => 'admin', 'param' => array(
                        'action' => 'auth'))));

        if (isset(Metadata::$objects['lang'])) {
            $lang_list = Db::selectAll('select * from lang order by lang_default desc');

            foreach ($lang_list as $lang) {
                $site_lang = $lang;

                $dictionary = Db::selectAll("
                    select
                        dictionary.word_name, translate.record_value
                    from
                        dictionary, translate
                    where
                        translate.table_record = dictionary.word_id and 
                        translate.table_name = 'dictionary' and
                        translate.field_name = 'word_value' and
                        translate.record_lang = :lang_id", array('lang_id' => $lang['lang_id']));

                foreach ($dictionary as $word) {
                    $site_lang['dictionary'][$word['word_name']] = $word['record_value'];
                }

                $site['lang'][] = $site_lang;
            }
        }

        if (isset(Metadata::$objects['preference'])) {
            $preference_list = Db::selectAll('select * from preference');

            foreach ($preference_list as $preference) {
                $site['preference'][$preference['preference_name']] = $preference['preference_value'];
            }
        }

        Cache::set(self::$key_name, $site);

        return $site;
    }

    // Обработка исключений в зависимости от типа и среды
    public static function exceptionHandler($e, $return = false, $admin = false)
    {
        $error_view = new View();
        $error_plug = $error_view->fetch('block/error');

        $error_view->assign('message', $e->getMessage());
        $error_short = $error_view->fetch('block/error');

        $error_view->assign('exception', $e);
        $error_content = $error_view->fetch('block/error');

        $error_log = date('d.m.Y H:i:s') . ' - ' . $e->getMessage() . "\n" .
                $e->getFile() . ' (' . $e->getLine() . ')' . "\n" . $e->getTraceAsString() . "\n\n";
        $error_file = LOG_DIR . $_SERVER['HTTP_HOST'] . '.log';

        if (PRODUCTION) {
            if (!$admin || !$return) {
                @file_put_contents($error_file, $error_log, FILE_APPEND);
                @Mail::send(ERROR_EMAIL, ERROR_EMAIL, $_SERVER['HTTP_HOST'], ERROR_SUBLECT, $error_content);
            }

            if ($admin) {
                $error_content = $return ? $error_short : $error_plug;
            } else {
                $error_content = $return ? '' : $error_plug;
            }
        }

        if (ob_get_length() !== false) {
            ob_clean();
        }

        if ($return) {
            return $error_content;
        }

        die($error_content);
    }

    public static function setCacheMode()
    {
        if (!isset($_SESSION['_cache_mode'])) {
            $_SESSION['_cache_mode'] = CACHE_SITE;
        }

        if (isset($_REQUEST['cache_on'])) {
            $_SESSION['_cache_mode'] = true;
            self::redirectTo(self::requestUrl(array(), array('cache_on')));
        }

        if (isset($_REQUEST['cache_off'])) {
            $_SESSION['_cache_mode'] = false;
            self::redirectTo(self::requestUrl(array(), array('cache_off')));
        }

        if (isset($_REQUEST['cache_clear'])) {
            Cache::clear();
            self::redirectTo(self::requestUrl(array(), array('cache_clear')));
        }

        self::$cache_mode = $_SESSION['_cache_mode'];
    }

    public static function notFound()
    {
        header('HTTP/1.0 404 Not Found');

        print View::block('404');

        exit;
    }
    
    public static function getParam($param_name, $param_value = null)
    {
        if (isset(self::$params[$param_name])) {
            return self::$params[$param_name];
        } else {
            return $param_value;
        }
    }

    public static function controller()
    {
        return self::getParam('controller');
    }

    public static function action()
    {
        return self::getParam('action', 'index');
    }

    public static function id()
    {
        return self::getParam('id', '');
    }

    public static function object()
    {
        return self::getParam('object');
    }

    public static function isCache()
    {
        return self::$cache_mode;
    }

    public static function lang()
    {
        return self::$lang;
    }

    public static function langList()
    {
        return self::$lang_list;
    }

    public static function page()
    {
        return self::$page;
    }

    public static function site()
    {
        return self::$site;
    }

    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
    }
}
