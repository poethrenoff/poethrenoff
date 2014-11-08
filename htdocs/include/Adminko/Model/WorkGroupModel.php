<?php
namespace Adminko\Model;

use Adminko\System;

class WorkGroupModel extends HierarchyModel
{
    public function getGroupUrl()
    {
        return System::urlFor(array('action' => 'group', 'id' => $this->getId()));
    }

    public function getPathTitle()
    {
        if ($this->is_new) {
            return 'Творчество';
        } else {
            return $this->getGroupTitle();
        }
    }

    public function getPathUrl()
    {
        if ($this->is_new) {
            return System::urlFor(array('action' => 'index'));
        } else {
            return $this->getGroupUrl();
        }
    }
}
