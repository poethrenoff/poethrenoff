<?php
namespace Adminko\Field;

use Adminko\Valid\Valid;

class PkField extends IntField
{
    public function check($errors = array()) {
        return Valid::factory('require')->check($this->get()) &&
            parent::check($errors) && ($this->get() > 0);
    }
}
