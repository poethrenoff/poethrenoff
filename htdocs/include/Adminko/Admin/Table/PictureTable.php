<?php
namespace Adminko\Admin\Table;

use Adminko\Db\Db;
use Adminko\Date;

class PictureTable extends Table
{
    protected function actionAddSave($redirect = true)
    {
        $picture_date = new \DateTime(init_string('picture_date'));
        $this->fields['picture_image']['upload_dir'] =
            $this->fields['picture_source']['upload_dir'] .= $picture_date->format('/Y/m/d');

        $primary_field = parent::actionAddSave(false);

        if ($redirect) {
            $this->redirect();
        }

        return $primary_field;
    }

    protected function actionEditSave($redirect = true)
    {
        $picture_date = new \DateTime(init_string('picture_date'));
        $this->fields['picture_image']['upload_dir'] =
            $this->fields['picture_source']['upload_dir'] .= $picture_date->format('/Y/m/d');

        parent::actionEditSave(false);

        if ($redirect) {
            $this->redirect();
        }
    }
}
