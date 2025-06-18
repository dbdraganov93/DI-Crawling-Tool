<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    if (($context['APP_ENV'] ?? 'prod') === 'prod') {
        $context['dotenv_path'] = false; // Disable .env loading in prod
    }

    return new Kernel(
        $context['APP_ENV'] ?? 'prod',
        (bool) ($context['APP_DEBUG'] ?? false)
    );
};
