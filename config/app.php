<?php
declare(strict_types=1);

return [
    'base_url' => rtrim(getenv('SGEP_BASE_URL') ?: '', '/'),
    'mail_from' => getenv('SGEP_MAIL_FROM') ?: 'encuestas@localhost',
    'timezone' => getenv('SGEP_TIMEZONE') ?: 'America/Argentina/Buenos_Aires'
];
