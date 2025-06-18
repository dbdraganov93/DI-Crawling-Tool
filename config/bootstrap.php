<?php
use Symfony\Component\Dotenv\Dotenv;

$env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';

if ($env !== 'prod' && is_readable(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
