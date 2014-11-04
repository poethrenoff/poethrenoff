<?php
namespace Adminko\Module;

use Adminko\System;
use Adminko\Model\Model;

class TextModule extends Module
{
    protected function actionIndex()
    {
        try {
            $item = Model::factory('text')->get($this->getParam('id'));
        } catch (\AlarmException $e) {
            System::notFound();
        }
        
        $this->view->assign($item);
        $this->content = $this->view->fetch('module/text/item');
    }
    
    // Получение текста по тегу
    public static function getByTag($text_tag)
    {
        return Model::factory('text')->getByTag($text_tag)->getTextContent();
    }
}