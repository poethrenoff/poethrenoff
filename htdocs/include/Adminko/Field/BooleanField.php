<?php
namespace Adminko\Field;

class BooleanField extends IntField
{
    public function get() {
        return (int) $this->value;
    }
    
    public function form() {
        return is_null($this->value) ? null : (boolean) $this->value;
    }
    
    public function view() {
        return (boolean) $this->value ? 'да' : 'нет';
    }
    
    public function check($errors = array()) {
        return parent::check($errors) &&
            (in_array('require', $errors) ? (boolean) $this->value : true);
    }
}
