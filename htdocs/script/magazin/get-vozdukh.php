<?php
$site_url = 'http://www.litkarta.ru';

for ($year = 2006; $year < 2015; $year++) {
	$year_url = $site_url . '/projects/vozdukh/issues/?year=' . $year;
	$year_page = iconv('Windows-1251', 'UTF-8', file_get_contents($year_url));
	preg_match_all('/\<div\>\<a href=(.+)\>\<strong\>.+\<\/strong\>\<\/a\>/iU', $year_page, $issue_matches);
	foreach ($issue_matches[1] as $number_url) {
		$magazin_url = $site_url . $number_url;
		$magazin_page = iconv('Windows-1251', 'UTF-8', file_get_contents($magazin_url));
		preg_match('/\<td width="74%" class="left_tab">№(.+), (.+)\<\/td\>/isU', $magazin_page, $number_matches);
		preg_match_all('/\<a href=(' . preg_quote($number_url, '/'). '.+)\>\<strong\>.+\<\/strong\>\<\/a\>/iU', $magazin_page, $magazin_matches);
		
		$magazin_text = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=windows-1251" /></head><body>';
		foreach ($magazin_matches[1] as $issue_url) {
			$page_url = $site_url . $issue_url . 'view_print/';
			$page = file_get_contents($page_url);
			preg_match('/(\<div class="title"\>.+)\<\!\-\- BEGIN posleslovie \-\-\>/isU', $page, $matches);
			$magazin_text .= strip_tags($matches[1], '<p><div><b><i><em><strong><br><br/>');
		}
		
		$magazin_text .= '</body></html>';
		
		file_put_contents(iconv('UTF-8', 'Windows-1251', "Воздух {$number_matches[2]}-{$number_matches[1]}.html"), $magazin_text);
	}
}
