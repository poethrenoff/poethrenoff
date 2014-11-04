<?php
namespace Adminko\Field;

use Adminko\Valid\Valid;

class IntField extends Field
{
    public function set($content) {
        $this->value = (string) $content !== '' ? $content : null;
        return $this;
    }
    
    public function check($errors = array()) {
        return Valid::factory('int')->check($this->get()) &&
            parent::check($errors);
    }
}
