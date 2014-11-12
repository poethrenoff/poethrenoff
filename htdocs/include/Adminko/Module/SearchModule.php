<?php
namespace Adminko\Module;

use Adminko\Paginator;
use Adminko\Model\Model;

class SearchModule extends Module
{
    /**
     * Заполнение контента модуля
     */
    protected function actionIndex()
    {
        $count = max(intval($this->getParam('count')), 1);

        $search_text = trim(init_string('text'));

        if (!is_empty($search_text)) {
            $work_model = Model::factory('work');

            $search_count = $work_model->getCountByText($search_text);
            $pages = Paginator::create($search_count, array('by_page' => $count));
            $search_list = $work_model->getListByText($search_text, $pages['by_page'], $pages['offset']);

            foreach ($search_list as $search_item) {
                $search_item->setWorkText(
                    $this->prepareSearchResult($search_item->getWorkText(), $search_text)
                );
            }

            $this->view->assign('search_text', $search_text);
            $this->view->assign('search_list', $search_list);
            $this->view->assign('search_count', $search_count);
            $this->view->assign('search_index', $pages['offset'] + 1);

            $this->view->assign('pages', Paginator::fetch($pages));
        }

        $this->content = $this->view->fetch('module/search/search');
    }

    /**
     * Преобразование результатов поиска
     */
    protected function prepareSearchResult($result_text, $search_text, $search_limit = 100, $text_limit = 500)
    {
        $result_text = strip_tags($result_text);
        $search_words = $search_text !== '' ? preg_split('/\s+/isu', $search_text) : array();

        $result_text_length = mb_strlen($result_text);
        if ($result_text_length > $text_limit) {
            $result_text = mb_substr($result_text, 0, $text_limit - 3) . '...';
            $result_text_length = $text_limit;
        }
        
        $result_pos_min = $result_text_length;
        $result_pos_max = 0;

        $result_pos_find = false;

        foreach ($search_words as $search_word) {
            $result_left_pos = mb_stripos($result_text, $search_word);
            $result_right_pos = mb_strripos($result_text, $search_word);

            if (( $result_left_pos !== false ) && ( $result_right_pos !== false )) {
                if ($result_left_pos < $result_pos_min) {
                    $result_pos_min = $result_left_pos;
                }
                if ($result_right_pos > $result_pos_max) {
                    $result_pos_max = $result_right_pos + mb_strlen($search_word);
                }

                $result_pos_find = true;
            }
        }

        if ($result_pos_find) {
            $left_pos = max(0, $result_pos_min - $search_limit);
            $right_pos = min($result_pos_max + $search_limit - 1, $result_text_length - 1);
        } else {
            $left_pos = 0;
            $right_pos = min($search_limit - 1, $result_text_length - 1);
        }

        $result_text = mb_substr($result_text, $left_pos, $right_pos - $left_pos + 1);
        foreach ($search_words as $search_word) {
            $result_text = preg_replace('|(' . preg_quote($search_word) . ')|isu', '<b>\\1</b>', $result_text);
        }

        if ($left_pos != 0) {
            $result_text = '...' . $result_text;
        }
        if ($right_pos != $result_text_length - 1) {
            $result_text = $result_text . '...';
        }

        return $result_text;
    }
}
