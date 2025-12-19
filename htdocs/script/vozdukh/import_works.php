<?php

use Adminko\Db\Db;

include_once dirname(__FILE__) . '/../../config/config.php';
include_once dirname(__FILE__) . '/../../include/include.php';

const MAIN_URL = 'http://www.litkarta.ru/projects/vozdukh/';
const RUBRICS_URL = MAIN_URL . 'rubrics/';
const ISSUES_URL = MAIN_URL . 'issues/';

$numbersPage = file_get_contents(RUBRICS_URL);
preg_match_all(
    '/<a href=\/projects\/vozdukh\/issues\/(?<number>[0-9-]+)\/>/isU',
    $numbersPage,
    $numbersMatches,
    PREG_SET_ORDER
);
$numbers = array_reverse(array_unique(array_column($numbersMatches, 'number')));
foreach ($numbers as $number) {
    print $number . PHP_EOL;

    $numberPage = file_get_contents(ISSUES_URL . $number);
    preg_match_all(
        '/<a href=\/projects\/vozdukh\/issues\/' . $number . '\/(?<slug>[a-z0-9_-]+)\/>/isU',
        $numberPage,
        $numberMatches,
        PREG_SET_ORDER
    );
    $slugs = array_unique(array_column($numberMatches, 'slug'));
    foreach ($slugs as $slug) {
        $url = ISSUES_URL . $number . '/' . $slug . '/';
        $issue = Db::selectRow(
            'select * from vozdukh_work where work_url = :work_url',
            [
                'work_url' => $url,
            ]
        );
        if ($issue) {
            continue;
        }

        $textPage = iconv('windows-1251', 'utf-8', file_get_contents($url));

        preg_match(
            '/<div class="title">(?<title>.*)<\/div>/isU',
            $textPage,
            $textMatches
        );
        $title = trim($textMatches['title'] ?? '');
        if ($title == 'Хроника поэтического книгоиздания в аннотациях и цитатах' ||
            $title == 'Отзывы') {
            continue;
        }

        preg_match(
            '/<div class="subtitle"><strong>(?<subtitle>.*)<\/strong><\/div>/isU',
            $textPage,
            $textMatches
        );
        $subtitle = trim($textMatches['subtitle'] ?? '');

        preg_match(
            '/<td width="76%" style="border:0;">.*<strong>(?<author>.*)<\/strong>/isU',
            $textPage,
            $textMatches
        );
        $author = trim($textMatches['author'] ?? '');

        preg_match(
            '/<td class="text">(?<text>.*)<\/td>/isU',
            $textPage,
            $textMatches
        );
        $text = trim($textMatches['text'] ?? '');

        print "\t" . $author . ' ' . $title . PHP_EOL;

        Db::insert('vozdukh_work', [
            'work_title' => $title,
            'work_subtitle' => $subtitle,
            'work_author' => $author,
            'work_text' => $text,
            'work_url' => $url,
            'work_active' => true,
        ]);
    }
}
