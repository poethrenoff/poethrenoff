<?php
namespace Adminko\Module;

use Adminko\Date;
use Adminko\System;
use Adminko\Model\Model;
use Adminko\Cache\Cache;

class PictureModule extends Module
{
    // Галерея
    protected function actionIndex()
    {
        $picture_list = Model::factory('picture')->getList(
            ['picture_active' => 1],['picture_order' => 'asc']
        );

        $picture_by_date = [];
        foreach ($picture_list as $picture_item) {
            $picture_by_date[$picture_item->getPictureDate()][] = $picture_item;
        }
        krsort($picture_by_date);

        $this->view->assign('picture_by_date', $picture_by_date);
        $this->content = $this->view->fetch('module/picture/gallery');
    }
}
