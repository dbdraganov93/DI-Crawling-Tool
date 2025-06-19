<?php

putenv('APP_RUNTIME=Symfony\\Component\\Runtime\\SymfonyRuntime');
putenv('APP_BOOTSTRAP=config/bootstrap.php');

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
