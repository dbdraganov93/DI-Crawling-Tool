<?php
use Symfony\Component\Dotenv\Dotenv;

$appEnv = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null);

if (!$appEnv && is_readable(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
