<?php
function labo_log($channel, $message, array $context = array())
{
    $allowedChannels = array('core', 'database', 'security', 'audit', 'auth', 'plugin');
    if (!in_array($channel, $allowedChannels, true)) {
        $channel = 'core';
    }

    $requestId = isset($_SERVER['HTTP_X_REQUEST_ID']) ? $_SERVER['HTTP_X_REQUEST_ID'] : '';
    if (!is_string($requestId) || !preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $requestId)) {
        $requestId = bin2hex(random_bytes(8));
    }

    $entry = array(
        'time' => date('c'),
        'request_id' => $requestId,
        'message' => $message
    );

    if ($context !== array()) {
        $entry['context'] = $context;
    }

    $line = json_encode($entry, JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        $line = json_encode(array(
            'time' => date('c'),
            'request_id' => $requestId,
            'message' => 'Unable to encode log context'
        ));
    }

    $logFile = __DIR__ . '/../logs/' . $channel . '.log';
    if (@file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        error_log($line);
    }
}
