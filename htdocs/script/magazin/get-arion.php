<?php
$site_url = 'http://arion.ru';
$magazin_url = $site_url . '/magazine.php?year=2015&number=142';

while (true) {
    $magazin_page = iconv('Windows-1251', 'UTF-8', file_get_contents($magazin_url));

    $number_result = preg_match('/\<p class="title2"\>СОДЕРЖАНИЕ НОМЕРА\<br\>\<span class="text"\>№ (\d+), (\d+)\<\/span\>\<\/p\>/', $magazin_page, $number_matches);
    $year = $number_matches[2]; $number = $number_matches[1];

    preg_match_all('/\<a href="(\/mcontent\.php\?year=\d+&number=\d+&idx=\d+)"\>.+\<\/a\>/isU', $magazin_page, $magazin_matches, PREG_SET_ORDER);

    $magazin_text = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=windows-1251" /></head><body>';
    foreach ($magazin_matches as $magazin_match) {
        $page_url = $site_url . $magazin_match[1];
        $page = file_get_contents($page_url);
        preg_match('/(\<p class="title2"\>.+)\<img src="\/images\/spacer\.gif"/isU', $page, $matches);
        
        $magazin_text .= $matches[1];
    }
    $magazin_text .= '</body></html>';
    
    file_put_contents(iconv('UTF-8', 'Windows-1251', "Арион {$year}-{$number}.html"), $magazin_text);
    
    $next_result = preg_match('/\<td\>\<a href="(.+)" class="menu1"\>&lt;&lt; предыдущий номер\<\/a\>&nbsp;\<\/td\>/', $magazin_page, $next_matches);
    if (!$next_result) {
        exit;
    }
    
    $magazin_url = $site_url . $next_matches[1];
}


