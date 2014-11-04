<?php
namespace Adminko\Field;

class StringField extends Field
{
    protected $value = '';
    
    public function set($content) {
        $this->value = (string) $content !== '' ? (string) $content : '';
        return $this;
    }
    
    public function form() {
        return htmlspecialchars($this->get(), ENT_QUOTES, 'UTF-8');
    }
    
    public function view() {
        return htmlspecialchars($this->get(), ENT_QUOTES, 'UTF-8');
    }
}
