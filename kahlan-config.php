<?php
use Lead\Set\Set;
use Kahlan\Filter\Filters;
use Kahlan\Code\Code;
use Kahlan\Code\TimeoutException;

function defineGlobals($config = []) {
    $defaults = [
        'SERVER' => [
            'REQUEST_URI' => '/base/path/webroot/index.php/app?get=value',
            'SCRIPT_NAME' => '/base/path/webroot/index.php'
        ],
        'GET'   => ['get' => 'value'],
        'POST'  => ['post' => 'value'],
        'FILES' => [
            'file' => [
                'name' => 'file.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/private/var/tmp/phpows38J',
                'error' => 0,
                'size' => 418
            ]
        ]
    ];
    $config = Set::merge($defaults, $config);
    $_SERVER = $config['SERVER'];
    $_GET = $config['GET'];
    $_POST = $config['POST'];
    $_FILES = $config['FILES'];
    return $config;
}

$echo = null;
$httpecho = null;

Filters::apply($this, 'run', function($next) use (&$echo, &$httpecho) {
    $pipesEcho = [];
    $command = 'exec php ' . __DIR__ . '/spec/Server/echo.php';
    echo "Running echo server through command `{$command}`\n";
    $echo = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ], $pipesEcho);

    $pipesHttpEcho = [];
    $command = 'exec php -S 127.0.0.1:10080 ' . __DIR__ . '/spec/Server/httpecho.php';
    echo "Running HTTP server command `{$command}`\n";
    $httpecho = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ], $pipesHttpEcho);

    try {
        $fp = Code::spin(function() {
            return @fsockopen('127.0.0.1', 10000);
        }, 5, true);
        fclose($fp);
    } catch (TimeoutException $e) {
        echo "Unable to run the echo driver, abording.\n";
        proc_terminate($echo);
        exit(-1);
    }

    try {
        $fp = Code::spin(function() {
            return @fsockopen('127.0.0.1', 10080);
        }, 5, true);
        fclose($fp);
    } catch (TimeoutException $e) {
        echo "Unable to run the http driver, abording.\n";
        proc_terminate($httpecho);
        exit(-1);
    }
    return $next();
});

Filters::apply($this, 'stop', function($next) use (&$echo, &$httpecho) {
    proc_terminate($echo);
    proc_terminate($httpecho);
    return $next();
});
