<?php

/**
 * Shared PCRMs database connection.
 *
 * Configure with PCRMS_DB_HOST, PCRMS_DB_PORT, PCRMS_DB_NAME,
 * PCRMS_DB_USER, and PCRMS_DB_PASSWORD, or copy
 * config/database.example.php to config/database.php.
 */

$databaseConfig = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => '',
    'username' => '',
    'password' => '',
];

$localConfigPath = dirname(__DIR__) . '/config/database.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $databaseConfig = array_merge($databaseConfig, $localConfig);
    }
}

$environmentConfig = [
    'host' => 'PCRMS_DB_HOST',
    'port' => 'PCRMS_DB_PORT',
    'database' => 'PCRMS_DB_NAME',
    'username' => 'PCRMS_DB_USER',
    'password' => 'PCRMS_DB_PASSWORD',
];

foreach ($environmentConfig as $configKey => $environmentKey) {
    $environmentValue = getenv($environmentKey);
    if ($environmentValue !== false && $environmentValue !== '') {
        $databaseConfig[$configKey] = $environmentValue;
    }
}

$missingDatabaseValues = [];
foreach (['database', 'username', 'password'] as $requiredKey) {
    if (!isset($databaseConfig[$requiredKey]) || $databaseConfig[$requiredKey] === '') {
        $missingDatabaseValues[] = $requiredKey;
    }
}

if ($missingDatabaseValues) {
    throw new RuntimeException(
        'PCRMs database configuration is incomplete. See config/database.example.php.'
    );
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $connection = new mysqli(
        $databaseConfig['host'],
        $databaseConfig['username'],
        $databaseConfig['password'],
        $databaseConfig['database'],
        (int) $databaseConfig['port']
    );
    $connection->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('PCRMs database connection failed: ' . $exception->getMessage());
    throw new RuntimeException('The PCRMs database is temporarily unavailable.');
}

if (!function_exists('pcrms_statement_timeout')) {
    // Stops any single query after $seconds so one expensive search cannot tie
    // up the server. MySQL 5.7.8+ limits SELECTs in milliseconds; MariaDB 10.1+
    // limits all statements in seconds. Older servers keep their default.
    function pcrms_statement_timeout($connection, $seconds)
    {
        $isMariaDb = stripos($connection->server_info, 'MariaDB') !== false;
        try {
            $connection->query($isMariaDb
                ? 'SET SESSION max_statement_time = ' . (int) $seconds
                : 'SET SESSION max_execution_time = ' . ((int) $seconds * 1000));
        } catch (mysqli_sql_exception $exception) {
            error_log('PCRMs could not set a statement timeout: ' . $exception->getMessage());
        }
    }
}

pcrms_statement_timeout($connection, 15);

// Backward-compatible connection alias used by the query pages.
$mysqli = $connection;
