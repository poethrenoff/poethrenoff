<?php
namespace Adminko\Module;

use Adminko\Paginator;
use Adminko\Model\Model;

class PictureModule extends Module
{
    // Галерея
    protected function actionIndex()
    {
        $count = max(intval($this->getParam('count')), 1);

        $picture_model = Model::factory('picture');
        $picture_count = $picture_model->getCount(['picture_active' => 1]);
        $pages = Paginator::create($picture_count, array('by_page' => $count));
        $picture_list = Model::factory('picture')->getList(
            ['picture_active' => 1],
            ['picture_date' => 'desc', 'picture_order' => 'asc'],
            $pages['by_page'],
            $pages['offset']
        );

        $this->view->assign('picture_list', $picture_list);
        $this->view->assign('pages', Paginator::fetch($pages));
        $this->content = $this->view->fetch('module/picture/gallery');
    }
}
