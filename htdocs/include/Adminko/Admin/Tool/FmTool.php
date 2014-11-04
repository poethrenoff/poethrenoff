<?php
namespace Adminko\Admin\Tool;

use Adminko\System;
use Adminko\Paginator;
use Adminko\Upload;
use Adminko\Admin\Admin;

class FmTool extends Admin
{
    protected $upload_path = '/upload/';

    protected $records_per_page = 20;

    public function getUploadPath()
    {
        return realpath(filter_input(INPUT_SERVER, 'DOCUMENT_ROOT') . $this->upload_path) . DIRECTORY_SEPARATOR;
    }

    protected function actionIndex()
    {
        $real_upload_path = $this->getUploadPath();

        if (!file_exists($real_upload_path)) {
            if (!( @mkdir($real_upload_path, 0777, true) )) {
                throw new \AlarmException('Ошибка. Невозможно создать каталог "' . $real_upload_path . '".');
            }
        }

        if (!is_readable($real_upload_path)) {
            throw new \AlarmException('Ошибка. Невозможно прочитать каталог "' . $real_upload_path . '".');
        }

        $sort_field = init_string('sort_field');
        if (!in_array($sort_field, array('id', 'name', 'size', 'date'))) {
            $sort_field = 'name';
        }
        $sort_order = init_string('sort_order');
        if (!in_array($sort_order, array('asc', 'desc'))) {
            $sort_order = 'asc';
        }

        $records_header['id'] = array('title' => 'ID');
        $records_header['name'] = array('title' => 'Название', 'type' => 'string', 'main' => 1);
        $records_header['size'] = array('title' => 'Размер', 'type' => 'int');
        $records_header['date'] = array('title' => 'Дата', 'type' => 'datetime');
        $records_header['_action'] = array('title' => 'Действия');

        foreach (array('id', 'name', 'size', 'date') as $show_field) {
            $field_sort_order = $show_field == $sort_field && $sort_order == 'asc' ? 'desc' : 'asc';
            $records_header[$show_field]['sort_url'] = System::requestUrl(array('sort_field' => $show_field, 'sort_order' => $field_sort_order), array('page'));
            if ($show_field == $sort_field) {
                $records_header[$show_field]['sort_sign'] = $field_sort_order == 'asc' ? 'desc' : 'asc';
            }
        }

        $upload_dir = opendir($real_upload_path);

        $file_list = array();
        while (( $file = readdir($upload_dir) ) !== false) {
            $real_file_path = $real_upload_path . $file;
            if (is_file($real_file_path) && substr($file, 0, 1) != '.') {
                $file_list[] = array('name' => $file, 'size' => filesize($real_file_path), 'date' => filemtime($real_file_path));
            }
        }
        closedir($upload_dir);

        foreach ($file_list as $file_index => $file_item) {
            $file_list[$file_index]['id'] = $file_index + 1;
        }

        usort($file_list, function($a, $b) use ($sort_field, $sort_order) {
            if ($sort_field == 'size') {
                $result = strnatcmp($a[$sort_field], $b[$sort_field]);
            } else {
                $result = strcmp($a[$sort_field], $b[$sort_field]);
            }
            return (($sort_order == 'asc') ? 1 : -1) * $result;
        });

        $records_count = count($file_list);

        $pages = Paginator::create($records_count, array('by_page' => $this->records_per_page));

        foreach ($file_list as $file_index => $file_item) {
            if ($file_index >= $pages['current_page'] * $this->records_per_page &&
                    $file_index < ( $pages['current_page'] + 1 ) * $this->records_per_page) {
                $file_list[$file_index]['name'] = '<a href="' . $this->upload_path . urlencode($file_item['name']) . '">' . $file_item['name'] . '</a>';
                $file_list[$file_index]['date'] = str_replace(' ', '&nbsp;', date('d.m.Y H:i', $file_item['date']));
                $file_list[$file_index]['_action'] = array('delete' => array('title' => 'Удалить', 'url' =>
                        System::urlFor(array('object' => 'fm', 'action' => 'delete', 'file' => urlencode($file_item['name']))),
                        'event' => array('method' => 'onclick', 'value' => 'return confirm( \'Вы действительно хотите удалить этот файл?\' )')));
            } else {
                unset($file_list[$file_index]);
            }
        }

        $actions = array('add' => array('title' => 'Закачать файл', 'url' =>
            System::urlFor(array('object' => $this->object, 'action' => 'upload'))));

        $this->view->assign('title', $this->object_desc['title']);
        $this->view->assign('actions', $actions);
        $this->view->assign('records', $file_list);
        $this->view->assign('header', $records_header);
        $this->view->assign('counter', $records_count);

        $this->view->assign('pages', Paginator::fetch($pages, 'admin/pages'));

        $this->content = $this->view->fetch('admin/table');

        $this->storeState();
    }

    protected function actionDelete()
    {
        $file = urldecode(init_string('file'));

        $real_file_path = $this->getUploadPath() . $file;
        
        if ($real_file_path != realpath($real_file_path)) {
            throw new \AlarmException('Ошибка. Недопустимое имя файла "' . $real_file_path . '".');
        }

        if (!file_exists($real_file_path) || !is_file($real_file_path)) {
            throw new \AlarmException('Ошибка. Файл "' . $real_file_path . '" не существует.');
        }

        @unlink($real_file_path);

        if (file_exists($real_file_path)) {
            throw new \AlarmException('Ошибка. Невозможно удалить файл "' . $real_file_path . '".');
        }

        $this->redirect();
    }

    protected function actionUpload()
    {
        $action_title = 'Закачка файла';
        $form_url = System::urlFor(array('object' => 'fm', 'action' => 'upload_save'));

        $this->view->assign('record_title', $this->object_desc['title']);
        $this->view->assign('action_title', $action_title);
        $this->view->assign('form_url', $form_url);

        $this->view->assign('back_url', System::urlFor($this->restoreState()));

        $this->content = $this->view->fetch('admin/fm/upload');
        $this->output['meta_title'] .= ' :: ' . $action_title;
    }

    protected function actionUploadSave()
    {
        $field_name = 'file';

        if (isset($_FILES[$field_name . '_file']['name']) && $_FILES[$field_name . '_file']['name']) {
            $upload = Upload::process($field_name . '_file');
        } else {
            throw new \AlarmException('Ошибка. Отсутствует файл для закачки.');
        }

        $this->redirect();
    }

    protected function actionUploadFile()
    {
        $CKEditorFuncNum = intval(init_string('CKEditorFuncNum'));

        if (isset($_FILES['upload']['name']) && $_FILES['upload']['name']) {
            try {
                $upload = Upload::process('upload');
            } catch (\AlarmException $e) {
                die('<script type="text/javascript">alert( "Ошибка! ' . $e->getMessage() . '." ); window.parent.CKEDITOR.tools.callFunction(' . $CKEditorFuncNum . ', "", "");</script>');
            }
        } else {
            die('<script type="text/javascript">alert( "Ошибка! Отсутствует файл для закачки." ); window.parent.CKEDITOR.tools.callFunction(' . $CKEditorFuncNum . ', "", "");</script>');
        }

        die('<script type="text/javascript">window.parent.CKEDITOR.tools.callFunction(' . $CKEditorFuncNum . ', "' . $upload->getFileLink(true) . '", "");</script>');
    }
}
