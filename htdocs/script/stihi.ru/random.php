<?php
/**
 * Скрипт для перемешивания произведений на сайте "Стихи.ру"
 */
include_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';

$server = 'http://www.stihi.ru';
$login_url = '/cgi-bin/login/intro.pl';
$list_url = '/login/page.html?list';
$put_url = '/login/page.html?put';

$user_login = 'poethrenoff';
$user_password = '**********';
$user_agent = 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.14 Safari/534.24';

//    $server_rubric = '18'; // Всякое, прочее и остальное
$server_rubric = '19'; // Практика

$ch = curl_init();

curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, $user_agent );
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_POST, 1 );
curl_setopt($ch, CURLOPT_POSTFIELDS, "login={$user_login}&password={$user_password}" );
curl_setopt($ch, CURLOPT_URL, $server . $login_url);

$result = curl_exec($ch);

preg_match( '/(login=\w+;)/', $result, $login_match );
preg_match( '/(pcode=\d+;)/', $result, $pcode_match );

curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_COOKIE, $login_match[1] . ' ' . $pcode_match[1]);

// Получаем список произведений
curl_setopt($ch, CURLOPT_POST, 0 );
curl_setopt($ch, CURLOPT_URL, $server . $list_url . "&book={$server_rubric}" );
$result = curl_exec($ch);

preg_match_all('/\<a href="\/(\d+\/\d+\/\d+\/\d+)"\>(.+)\<\/a\>/isU', $result, $matches, PREG_SET_ORDER);

// Перемешиваем его
shuffle($matches);

foreach ($matches as $match) {
    curl_setopt($ch, CURLOPT_POST, 0 );
    curl_setopt($ch, CURLOPT_URL, $server . $put_url . "&link={$match[1]}&to={$server_rubric}" );
    curl_exec($ch);

    print iconv('Windows-1251', 'UTF-8', $match[2]) . "\t\t\t" . '[OK]' . "\n";
}
