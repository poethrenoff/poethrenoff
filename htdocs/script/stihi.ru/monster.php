<?php
/**
 * Скрипт для публикации произведений на сайте "Стихи.ру"
 */
include_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';

use Adminko\Db\Db;
use Adminko\Date;

$monster_list = Db::selectAll('select * from monster');

foreach ($monster_list as $monster_index => $monster_item) {
    print str_pad($monster_item['monster_login'], 20, ' ', STR_PAD_RIGHT); 
    
    if (!$monster_item['monster_active']) {
        print "---" . PHP_EOL; 
        continue;
    }

    $monster_url = getUrl($monster_item);
    
    $page = @file_get_contents($monster_url);            
    if (is_empty($page)) {
        throw new \AlarmException('Ошибка. Страница "' . $monster_url . '" недоступна.');
    }

    $count_result = preg_match('/' . iconv('UTF-8', 'Windows-1251', 'Произведений') . '\: \<b\>(\d+)\<\/b\>/', $page, $matches);
    if (!$count_result) {
        throw new \AlarmException('Ошибка при получении количества произведений на странице "' . $monster_url . '".');
    }
    $monster_list[$monster_index]['monster_count_old'] = $monster_list[$monster_index]['monster_count'];
    $monster_list[$monster_index]['monster_count'] = $matches[1];

    $date_result = preg_match_all('/\d{2}\.\d{2}\.\d{4} \d{2}\:\d{2}/', $page, $matches);
    if (!$date_result) {
        throw new \AlarmException('Ошибка при получении дат произведений на странице "' . $monster_url . '".');
    }
    $dates = $matches[0];
    usort($dates, function($a, $b) {
        return Date::set($b, 'long') > Date::set($a, 'long');
    });
    $monster_list[$monster_index]['monster_date'] = Date::set(current($dates), 'long');

    print "{$monster_list[$monster_index]['monster_count']}" . PHP_EOL;
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
