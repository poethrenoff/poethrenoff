<?php
namespace Adminko\Module;

use Adminko\Cache\cache;

class PolutonaModule extends Module
{
    const MAIN_URL = 'https://polutona.ru/';

    protected function actionIndex()
    {
        if (($authors = cache::get('authors', 3600)) === false) {
            $authors = file_get_contents(static::MAIN_URL . 'rss/rss-authors.php');
            cache::set('authors', $authors);
        }

        $authorsXml = simplexml_load_string($authors);
        $authorXml = $authorsXml->channel->item[rand(0, count($authorsXml->channel->item) - 1)];

        $authorPage = file_get_contents($authorXml->link);
        preg_match_all('/\<a href=\"\?show=(?<show>\d+)\"\>/isU',
            $authorPage, $authorWorks, PREG_SET_ORDER);
        if (!$authorWorks) {
            return;
        }

        $workPageLink = static::MAIN_URL . 'printer.php3?address=' .
            $authorWorks[rand(0, count($authorWorks) - 1)]['show'];
        $workPage = file_get_contents($workPageLink);

        preg_match('/\<body\>(?<text>.+)\<\/body\>/isU', $workPage, $text);
        $text = preg_replace('/\<h1>.*\<\/h1\>/isU', '',
            preg_replace('/\<script.*\<\/script\>/isU', '',
                preg_replace('/\<img.*\>/isU', '', $text['text'])));

        $this->view->assign('text', $text);
        $this->content = $this->view->fetch('module/polutona/view');
    }

    protected function getCacheKey()
    {
        return false;
    }
}
