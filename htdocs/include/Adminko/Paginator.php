<?php
namespace Adminko;

class Paginator
{
    public static function create($count, $pages = array())
    {
        if (!isset($pages['by_page'])) {
            $pages['by_page'] = 10;
        }
        if (!isset($pages['varname'])) {
            $pages['varname'] = 'page';
        }
        if (!isset($pages['url'])) {
            $pages['url'] = System::selfUrl();
        }

        $pages['count'] = max(1, intval($count));
        $pages['by_page'] = max(1, intval($pages['by_page']));

        $query_string = http_build_query(System::prepareQuery($_GET, array($pages['varname'])));
        $page_url = $pages['url'] . '?' . ($query_string ? $query_string . '&' : '') . $pages['varname'] . '=';

        $first_page = 0;
        $last_page = max(floor(($pages['count'] - 1) / $pages['by_page']), 0);
        $pages['current_page'] = min(max(intval(init_string($pages['varname'])), $first_page), $last_page);

        $pages['pages_count'] = $last_page + 1;
        $pages['offset'] = $pages['current_page'] * $pages['by_page'];

        $pages['pages'] = array();

        $section_length = 5;
        $section_first_page = max(0, min($pages['pages_count'] - $section_length, $pages['current_page'] - floor($section_length / 2)));
        $section_last_page = min(max($section_length, $pages['current_page'] + ceil($section_length / 2)), $pages['pages_count']);

        if ($section_first_page > 0) {
            $pages['pages'][] = array('number' => '1', 'link' => $page_url . '0');
        }
        if ($section_first_page > 1) {
            $pages['pages'][] = array('link' => $page_url . ($section_first_page - 1));
        }

        for ($p = $section_first_page; $p < $section_last_page; $p++) {
            $pages['pages'][] = array('number' => $p + 1, 'link' => $p != $pages['current_page'] ? $page_url . $p : null);
        }

        if ($section_last_page < $pages['pages_count'] - 1) {
            $pages['pages'][] = array('link' => $page_url . $section_last_page);
        }
        if ($section_last_page < $pages['pages_count']) {
            $pages['pages'][] = array('number' => $pages['pages_count'], 'link' => $page_url . ($pages['pages_count'] - 1));
        }

        if ($pages['current_page'] > 0) {
            $pages['prev_page'] = array('link' => $page_url . ($pages['current_page'] - 1));
        }
        if ($pages['current_page'] < $pages['pages_count'] - 1) {
            $pages['next_page'] = array('link' => $page_url . ($pages['current_page'] + 1));
        }

        return $pages;
    }

    public static function fetch($pages, $tpl = 'block/pages')
    {
        $view = new View();
        $view->assign($pages);

        return $view->fetch($tpl);
    }
}
