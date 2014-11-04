<?php
// Автозагрузчик по стандарту PSR-0
spl_autoload_register(function ($class_name) {
    $class_path = join(DIRECTORY_SEPARATOR, explode('\\', $class_name));

    if (file_exists($class_file = CLASS_DIR . $class_path . '.php')) {
        include_once($class_file);
    }
});
