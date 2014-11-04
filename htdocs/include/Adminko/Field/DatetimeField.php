<?php
namespace Adminko\Field;

use Adminko\Date;
use Adminko\Valid\Valid;

class DatetimeField extends StringField
{
    public function parse($content) {
        $result = Valid::factory('datetime')->parse_check($content);
        if ($result) {
            $this->set(Date::set($content, 'long'));
        }
        return $result;
    }
    
    public function form() {
        return Date::get($this->get(), 'long');
    }
    
    public function view() {
        return preg_replace('/\s+/', '&nbsp;', Date::get($this->get(), 'long'));
    }
    
    public function check($errors = array()) {
        return Valid::factory('datetime')->check($this->get()) &&
            parent::check($errors);
    }
}
