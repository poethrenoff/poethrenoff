<?php
namespace Adminko\Field;

use Adminko\Valid\Valid;

abstract class Field
{
    // Значение поля
    protected $value = null;
    
    ///////////////////////////////////////////////////////////////////////////
    
    // Конвертирует значение поля из внешнего представления во внутреннее
    public function parse($content) {
        $this->set($content);
        return true;
    }
    
    // Устанавливает значение поля во внутреннем формате
    public function set($content) {
        $this->value = $content;
        return $this;
    }
    
    // Возвращает значение поля во внутреннем формате
    public function get() {
        return $this->value;
    }
    
    // Возвращает значение поля для вывода в форме
    public function form() {
        return $this->get();
    }
    
    // Возвращает значение поля для вывода в списке
    public function view() {
        return $this->get();
    }
    
    // Проверяет валидности поля
    public function check($errors = array()) {
        foreach ($errors as $error) {
            if (!Valid::factory($error)->check($this->get())) {
                return false;
            }
        }
        return true;
    }
    
    // Создание объекта поля
    public static final function factory($type)
    {
        $class_namespace = 'Field';
        $class_name = __NAMESPACE__ . '\\' . to_class_name($type) . $class_namespace;
        
        if (!class_exists($class_name)) {
            throw new \Exception('Ошибка. Класс "' . $class_name . '" не найден.');
        }
        
        return new $class_name();
    }
}
