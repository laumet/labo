<?php
require_once __DIR__ . '/logger.php';

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

$databaseConfiguration = array(
    'host' => getenv('DB_HOST'),
    'name' => getenv('DB_NAME'),
    'user' => getenv('DB_USER'),
    'password' => getenv('DB_PASS')
);

 $missingConfiguration = array();
foreach ($databaseConfiguration as $name => $value) {
    if ($value === false || $value === '') {
        $missingConfiguration[] = $name;
    }
}

if ($missingConfiguration !== array()) {
    labo_log('database', 'Database connection configuration is incomplete.', array(
        'missing' => $missingConfiguration
    ));
    http_response_code(500);
    exit('Database connection unavailable.');
}

try {
    $db = new PDO(
        'mysql:host=' . $databaseConfiguration['host'] . ';dbname=' . $databaseConfiguration['name'] . ';charset=utf8mb4',
        $databaseConfiguration['user'],
        $databaseConfiguration['password'],
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
} catch (PDOException $e) {
    labo_log('database', 'Database connection failed.', array(
        'error' => $e->getMessage()
    ));
    http_response_code(500);
    exit('Database connection unavailable.');
}