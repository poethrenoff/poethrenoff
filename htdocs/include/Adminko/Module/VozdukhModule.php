<?php
namespace Adminko\Module;

use Adminko\Cache\cache;

class VozdukhModule extends Module
{
    const MAIN_URL = 'http://www.litkarta.ru/projects/vozdukh/issues/';

    protected function actionIndex()
    {
        if (($numbersPage = cache::get('vozdukh:numbers', 3600)) === false) {
            $numbersPage = file_get_contents(static::MAIN_URL);
            cache::set('vozdukh:numbers', $numbersPage);
            print 1;
        }
        preg_match_all(
            '/<a href=\/projects\/vozdukh\/issues\/(?<number>[0-9-]+)\/>/isU',
            $numbersPage,
            $numbersMatches,
            PREG_SET_ORDER
        );
        $numbersItem = $numbersMatches[rand(0, count($numbersMatches) - 1)];
        $number = $numbersItem['number'];

        $numberPage = file_get_contents(static::MAIN_URL . $number);
        preg_match_all(
            '/<a href=\/projects\/vozdukh\/issues\/' . $number . '\/(?<slug>\w+)\/>/isU',
            $numberPage,
            $numberMatches,
            PREG_SET_ORDER
        );
        $numberItem = $numberMatches[rand(0, count($numberMatches) - 1)];
        $slug = $numberItem['slug'];

        $link = static::MAIN_URL . $number . '/' . $slug;
        $textPage = iconv('windows-1251', 'utf-8', file_get_contents($link));

        preg_match(
            '/<div class="title">(?<title>.*)<\/div>/isU',
            $textPage,
            $textMatches
        );
        $title = $textMatches['title'] ?? '';

        preg_match(
            '/<div class="subtitle"><strong>(?<subtitle>.*)<\/strong><\/div>/isU',
            $textPage,
            $textMatches
        );
        $subtitle = $textMatches['subtitle'] ?? '';

        preg_match(
            '/<td width="76%" style="border:0;">.*<strong>(?<author>.*)<\/strong>/isU',
            $textPage,
            $textMatches
        );
        $author = $textMatches['author'] ?? '';

        preg_match(
            '/<td class="text">(?<text>.*)<\/td>/isU',
            $textPage,
            $textMatches
        );
        $text = $textMatches['text'] ?? '';

        $this->view->assign('title', $title);
        $this->view->assign('subtitle', $subtitle);
        $this->view->assign('author', $author);
        $this->view->assign('text', $text);
        $this->view->assign('link', $link);
        $this->content = $this->view->fetch('module/vozdukh/view');
    }

    protected function getCacheKey()
    {
        return false;
    }
}
