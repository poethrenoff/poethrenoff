<?php
namespace Adminko\Field;

use Adminko\Date;
use Adminko\Valid\Valid;

class DateField extends StringField
{
    public function parse($content) {
        $result = Valid::factory('date')->parse_check($content);
        if ($result) {
            $this->set(Date::set($content, 'short'));
        }
        return $result;
    }
    
    public function form() {
        return Date::get($this->get(), 'short');
    }
    
    public function view() {
        return preg_replace('/\s+/', '&nbsp;', Date::get($this->get(), 'short'));
    }
    
    public function check($errors = array()) {
        return Valid::factory('date')->check($this->get()) &&
            parent::check($errors);
    }
}
