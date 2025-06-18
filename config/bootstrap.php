<?php

use Symfony\Component\Dotenv\Dotenv;

if (!getenv('APP_ENV') && !isset($_ENV['APP_ENV']) && !isset($_SERVER['APP_ENV'])) {
    $envFile = dirname(__DIR__).'/.env';
    if (is_readable($envFile)) {
        (new Dotenv())->bootEnv($envFile);
    }
}
