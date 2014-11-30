<?php
/**
 * Скрипт для создания книг стихов
 * 
 * @file 2fb2f.php
 * @version 0.4
 */
$genre = 'poetry';

$author_first_name = 'Поэт Хренов';

$lang = 'ru';

$first_name = 'Дмитрий';
$last_name = 'Константинов';
$nickname = 'poethrenoff';

$program_name = basename(__FILE__);
$program_version = '0.4';

$src_url = 'http://www.stihi.ru/avtor/poethrenoff';
$version = '1.0';

if (!isset($argv[1])) {
	exit;
}

$text = file_get_contents( $argv[1] );
$text = iconv('windows-1251', 'UTF-8', $text);

$text_list = explode("\r\n\r\n\r\n", $text);

$work_list = array();
foreach ( $text_list as $text_item ) {
	$content = trim( $text_item );
	
	$title_index = strpos( $content, "\n" );
	$title = trim( substr( $content, 0, $title_index ) );
	$title_upper = mb_strtoupper($title, 'UTF-8');

	if ($title == $title_upper) {
		$content = trim( substr( $content, $title_index + 1 ) );
	} else {
		$title = '* * *';
	}
	
	$work_list[] = array($title, $content);    
}

$title = iconv('windows-1251', 'UTF-8', pathinfo($argv[1], PATHINFO_FILENAME));

$dom_xml = new DOMDocument();

$dom_xml->encoding = 'UTF-8';
$dom_xml->formatOutput = true;

$FictionBook_xml = $dom_xml->createElement('FictionBook'); $dom_xml->appendChild($FictionBook_xml);
	$FictionBook_xml->setAttribute('xmlns', 'http://www.gribuser.ru/xml/fictionbook/2.0');
	$FictionBook_xml->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
	
	$description_xml = $dom_xml->createElement('description'); $FictionBook_xml->appendChild($description_xml);
		$title_info_xml = $dom_xml->createElement('title-info'); $description_xml->appendChild($title_info_xml);
			$genre_xml = $dom_xml->createElement('genre', $genre); $title_info_xml->appendChild($genre_xml);
			$author_xml = $dom_xml->createElement('author'); $title_info_xml->appendChild($author_xml);
				$first_name_xml = $dom_xml->createElement('first-name', $author_first_name); $author_xml->appendChild($first_name_xml);
			$book_title_xml = $dom_xml->createElement('book-title', $title); $title_info_xml->appendChild($book_title_xml);
			if (isset($argv[2])) {
				$coverpage_xml = $dom_xml->createElement('coverpage'); $title_info_xml->appendChild($coverpage_xml);
					$image_xml = $dom_xml->createElement('image'); $coverpage_xml->appendChild($image_xml);
						$image_xml->setAttribute('xlink:href', '#' . pathinfo($argv[2], PATHINFO_BASENAME));
			}
			$lang_xml = $dom_xml->createElement('lang', $lang); $title_info_xml->appendChild($lang_xml);
		
		$document_info_xml = $dom_xml->createElement('document-info'); $description_xml->appendChild($document_info_xml);
			$author_xml = $dom_xml->createElement('author'); $document_info_xml->appendChild($author_xml);
				$first_name_xml = $dom_xml->createElement('first-name', $first_name); $author_xml->appendChild($first_name_xml);
				$last_name_xml = $dom_xml->createElement('last-name', $last_name); $author_xml->appendChild($last_name_xml);
				$nickname_xml = $dom_xml->createElement('nickname', $nickname); $author_xml->appendChild($nickname_xml);
			$program_used_xml = $dom_xml->createElement('program-used', $program_name . ' ' . $program_version); $document_info_xml->appendChild($program_used_xml);
			$date_xml = $dom_xml->createElement('date', date('d F Y')); $document_info_xml->appendChild($date_xml);
				$date_xml->setAttribute('value', date('Y-m-d'));
			$src_url_xml = $dom_xml->createElement('src-url', $src_url); $document_info_xml->appendChild($src_url_xml);
			$id_xml = $dom_xml->createElement('id', create_uid()); $document_info_xml->appendChild($id_xml);
			$version_xml = $dom_xml->createElement('version', $version); $document_info_xml->appendChild($version_xml);
			
	$body_xml = $dom_xml->createElement('body'); $FictionBook_xml->appendChild($body_xml);
		$title_xml = $dom_xml->createElement('title'); $body_xml->appendChild($title_xml);
			$p_xml = $dom_xml->createElement('p', $author_first_name); $title_xml->appendChild($p_xml);
			$p_xml = $dom_xml->createElement('empty-line'); $title_xml->appendChild($p_xml);
			$p_xml = $dom_xml->createElement('p', $title); $title_xml->appendChild($p_xml);

foreach ($work_list as $work) {
	$section_xml = $dom_xml->createElement('section'); $body_xml->appendChild($section_xml);
	
	$title_xml = $dom_xml->createElement('title'); $section_xml->appendChild($title_xml);
		$p_xml = $dom_xml->createElement('p', $work[0]); $title_xml->appendChild($p_xml);
	
	$poem_xml = $dom_xml->createElement('poem'); $section_xml->appendChild($poem_xml);
	
	$stanzes = explode("\r\n\r\n", $work[1]);
	foreach ($stanzes as $stanza) {
		$stanza_xml = $dom_xml->createElement('stanza'); $poem_xml->appendChild($stanza_xml);
		
		$lines = explode("\r\n", $stanza);
		foreach ($lines as $line) {
			$line  = preg_replace_callback ('/^ +| {2,}/m', create_function(
				'$matches', 'return str_repeat( \'  \', strlen($matches[0]) );'
			), $line);
			
			$v_xml = $dom_xml->createElement('v', $line); $stanza_xml->appendChild($v_xml);
		}
	}
}

if (isset($argv[2])) {
	$binary_xml = $dom_xml->createElement('binary', base64_encode(file_get_contents($argv[2]))); $FictionBook_xml->appendChild($binary_xml);
		$binary_xml->setAttribute('id', pathinfo($argv[2], PATHINFO_BASENAME));
		$binary_xml->setAttribute('content-type', 'image/jpeg');
}

file_put_contents(dirname(__FILE__) . '/' . iconv('UTF-8', 'windows-1251', $title) . '.fb2', $dom_xml->saveXML());

function create_uid() {
	return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}
