<?php
use Lead\Set\Set;

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
