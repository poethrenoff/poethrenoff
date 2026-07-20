<?php

use App\Kernel;

$_SERVER['APP_SITE_CONTEXT'] = 'blog';

require_once dirname(__DIR__, 2).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
