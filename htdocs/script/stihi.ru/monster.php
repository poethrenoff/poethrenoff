<?php
/**
 * Скрипт для публикации произведений на сайте "Стихи.ру"
 */
include_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';

use Adminko\Db\Db;
use Adminko\Date;

$monster_list = Db::selectAll('select * from monster');

foreach ($monster_list as $monster_index => $monster_item) {
    print str_pad($monster_item['monster_login'], 20);

    try {
        $monster_url = getUrl($monster_item);
        
        $page = @iconv('Windows-1251', 'UTF-8', @file_get_contents($monster_url));
        if (is_empty($page)) {
            throw new \AlarmException('Страница недоступна');
        }

        $count_result = preg_match('/Произведений\: \<b\>(\d+)\<\/b\>/', $page, $matches);
        if (!$count_result) {
            throw new \AlarmException('Ошибка при получении количества произведений');
        }
        $monster_item['monster_count_old'] = $monster_item['monster_count'];
        $monster_item['monster_count'] = $matches[1];

        $title_result = preg_match('/\<h1\>(.+)\<\/h1\>/', $page, $matches);
        if (!$title_result) {
            throw new \AlarmException('Ошибка при получении имени автора');
        }
        $monster_item['monster_title'] = $matches[1];

        $date_result = preg_match_all('/\d{2}\.\d{2}\.\d{4} \d{2}\:\d{2}/', $page, $matches);
        if (!$date_result) {
            throw new \AlarmException('Ошибка при получении дат произведений');
        }
        $dates = $matches[0];
        usort($dates, function($a, $b) {
            return Date::set($b, 'long') > Date::set($a, 'long');
        });
        $monster_item['monster_date'] = Date::set(current($dates), 'long');
        
        $monster_item['monster_active'] = 1;
        $monster_list[$monster_index] = $monster_item;
        
        print "{$monster_item['monster_count']}" . PHP_EOL;
    } catch (\AlarmException $e) {
        $monster_list[$monster_index]['monster_active'] = 0;
        
        print $e->getMessage(). PHP_EOL; 
    }
}

usort($monster_list, function($a, $b) {
    return $b['monster_count'] > $a['monster_count'];
});

$monster_place = 0;
foreach ($monster_list as $monster_index => $monster_item) {
    $monster_list[$monster_index]['monster_place_old'] = $monster_list[$monster_index]['monster_place'];
    $monster_list[$monster_index]['monster_place'] = ++$monster_place;

    Db::update('monster', $monster_list[$monster_index], array('monster_id' => $monster_item['monster_id']));
}

function getUrl(&$monster_item)
{
    return "http://stihi.ru/avtor/" . $monster_item['monster_login'];
}
