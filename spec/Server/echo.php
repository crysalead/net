<?php
error_reporting(E_ALL);
set_time_limit(0);

ob_implicit_flush();

$backlog = 5;
$timeout = 10;
$bufferSize = 1024;
$address = '127.0.0.1';
$port = 10000;

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo socket_strerror(socket_last_error()) . "\n";
    exit(-1);
}

if (socket_bind($sock, $address, $port) === false) {
    echo socket_strerror(socket_last_error($sock)) . "\n";
    exit(-1);
}

if (socket_listen($sock, $backlog) === false) {
    echo socket_strerror(socket_last_error($sock)) . "\n";
    exit(-1);
}

$clients = [];

do {
    $read = [];
    $read[] = $sock;

    $read = array_merge($read, $clients);

    if (socket_select($read, $write, $except, $timeout) < 1)
    {
        continue;
    }

    if (in_array($sock, $read)) {

        if (($client = socket_accept($sock)) === false) {
            echo socket_strerror(socket_last_error($sock)) . "\n";
            break;
        }
        $clients[] = $client;
        $welcome = "TCP Ping/Pong Server\r\n";
        socket_write($client, $welcome, strlen($welcome));
    }

    foreach ($clients as $key => $client) {
        if (in_array($client, $read)) {
            if (($buffer = @socket_read($client, $bufferSize, PHP_NORMAL_READ)) === false) {
                unset($clients[$key]);
            }
            if (!$buffer = trim($buffer)) {
                continue;
            }
            if ($buffer === 'exit') {
                socket_shutdown($client);
                unset($clients[$key]);
                socket_close($client);
                break;
            }
            $talkback = "Received: '{$buffer}'\r\n";
            socket_write($client, $talkback, strlen($talkback));
        }
    }
} while (true);

socket_close($sock);
?>