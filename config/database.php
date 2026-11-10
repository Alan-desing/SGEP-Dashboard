<?php
declare(strict_types=1);

return [
    'host' => getenv('SGEP_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('SGEP_DB_PORT') ?: '3306',
    'database' => getenv('SGEP_DB_NAME') ?: 'sgep_dashboard',
    'username' => getenv('SGEP_DB_USER') ?: 'root',
    'password' => getenv('SGEP_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4'
];
