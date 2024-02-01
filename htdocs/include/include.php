<?php
// Подключение хелперов
include_once 'helpers.php';

// Подключение автозагрузчиков
include_once 'autoload.php';
include_once dirname(__FILE__) . '/../vendor/autoload.php';

// Подключение пользовательских исключений
include_once 'exception.php';

Adminko\System::init();
