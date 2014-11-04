<?php
namespace Adminko;

abstract class Object
{
    // Имя объекта
    protected $object = '';
    
    // Объект шаблонизатора
    protected $view = null;
    
    // Результат работы объекта
    protected $content = '';
    
    // Данные для передачи в шаблон
    protected $output = array();
    
    ////////////////////////////////////////////////////////////////////////////////////////////////
    
    // Конструктор объекта
    public function __construct($object)
    {
        $this->object = $object;
    }
    
    // Возвращает результат работы объекта
    public function getContent()
    {
        return $this->content;
    }
    
    // Возвращает данные для передачи в шаблон
    public function getOutput()
    {
        return $this->output;
    }
}
