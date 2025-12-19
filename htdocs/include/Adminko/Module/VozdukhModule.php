<?php
namespace Adminko\Module;

use Adminko\Db\Db;

class VozdukhModule extends Module
{
    protected function actionIndex()
    {
        $issue = Db::selectRow(
            'select * from vozdukh_work where work_active = :work_active order by rand()',
            [
                'work_active' => true,
            ]
        );

        $this->view->assign('title', $issue['work_title']);
        $this->view->assign('subtitle', $issue['work_subtitle']);
        $this->view->assign('author', $issue['work_author']);
        $this->view->assign('text', $issue['work_text']);
        $this->view->assign('link', $issue['work_url']);
        $this->content = $this->view->fetch('module/vozdukh/view');
    }

    protected function getCacheKey()
    {
        return false;
    }
}
