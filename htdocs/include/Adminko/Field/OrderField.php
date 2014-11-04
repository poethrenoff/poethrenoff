<?php
namespace Adminko\Field;

use Adminko\Valid\Valid;

class OrderField extends IntField
{
    protected $value = 0;
    
    public function set($content) {
        $this->value = (string) $content !== '' ? $content : 0;
        return $this;
    }
    
    public function check($errors = array()) {
        return Valid::factory('require')->check($this->get()) &&
            parent::check($errors);
    }
}
