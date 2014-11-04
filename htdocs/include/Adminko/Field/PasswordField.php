<?php
namespace Adminko\Field;

use Adminko\Valid\Valid;

class PasswordField extends StringField
{
    public function parse($content) {
        $result = Valid::factory('alpha')->parse_check($content);
        if ($result) {
            $this->set(md5($content));
        }
        return $result;
    }
    
    public function form() {
        return '';
    }
    
    public function view() {
        return str_repeat('*', rand(5, 8));
    }
}
