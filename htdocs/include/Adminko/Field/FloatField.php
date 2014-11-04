<?php
namespace Adminko\Field;

use Adminko\Valid\Valid;

class FloatField extends Field
{
    public function set($content) {
        $this->value = (string) $content !== '' ? str_replace(',', '.', $content) : null;
        return $this;
    }
    
    public function check($errors = array()) {
        return Valid::factory('float')->check($this->get()) &&
            parent::check($errors);
    }
}
