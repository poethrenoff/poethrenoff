<?php
namespace Adminko\Admin\Tool;

use Adminko\System;
use Adminko\Shingles;
use Adminko\Db\Db;
use Adminko\Admin\Admin;

class SimilarityTool extends Admin
{
    protected function actionIndex()
    {
        $this->view->assign('title', $this->object_desc['title']);

        $form_url = System::urlFor(array('object' => 'similarity', 'action' => 'check'));
        $this->view->assign('form_url', $form_url);

        $this->content = $this->view->fetch('admin/similarity/similarity');

        $this->storeState();
    }

    protected function actionCheck()
    {
        $text = init_string('text');
        if ($text === '') {
            throw new \AlarmException('Ошибка. Не заполнено поле "Текст".');
        }

        for ($i = 1; $i <= shingles::SHINGLES_COUNT; $i++) {
            $similarity[$i] = array();
            $shingles = array_unique(Shingles::getShingles($text, $i));
            foreach ($shingles as $shingle) {
                $shingle_list = Db::selectAll('
                    select * from work_shingle where shingle_length = :shingle_length and shingle_value = :shingle_value', array('shingle_length' => $i, 'shingle_value' => $shingle)
                );
                foreach ($shingle_list as $shingle_row) {
                    @$similarity[$i][$shingle_row['work_id']] += $shingle_row['shingle_weight'];
                }
            }
            arsort($similarity[$i]);
        }

        $similar_work_id = 0;
        $similar_length = 0;
        for ($i = Shingles::SHINGLES_COUNT; $i >= 1; $i--) {
            if (!empty($similarity[$i])) {
                $similar_work_id = current(
                    array_keys($similarity[$i])
                );
                $similar_length = $i;
                break;
            }
        }

        if ($similar_work_id) {
            $work_item = Db::selectRow('
                select * from work where work_id = :work_id and work_active = 1', array('work_id' => $similar_work_id));

            $first_shingles = array_unique(Shingles::getShingles($text, $similar_length));
            $second_shingles = array_unique(Shingles::getShingles($work_item['work_text'], $similar_length));
            $intersect = array_intersect($first_shingles, $second_shingles);
            $merge = array_unique(array_merge($first_shingles, $second_shingles));

            $work_item['work_text'] = preg_replace_callback('/^ +| {2,}/m', create_function(
                '$matches', 'return str_repeat( \'&nbsp;\', strlen($matches[0]) );'
            ), $work_item['work_text']);

            $this->view->assign($work_item);
            $this->view->assign('result_percent', round((count($intersect) / count($merge)) / 0.01, 2));
            $this->view->assign('result_words', join(', ', array_intersect($first_shingles, $second_shingles)));
        }

        $this->view->assign('title', $this->object_desc['title']);

        $form_url = System::urlFor(array('object' => 'similarity', 'action' => 'check'));
        $this->view->assign('form_url', $form_url);

        $this->content = $this->view->fetch('admin/similarity/similarity');

        $this->storeState();
    }
}
