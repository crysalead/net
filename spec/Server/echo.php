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

$clients = [$sock];

do {

    $read = $clients;

    if (socket_select($read, $write, $except, $timeout) < 1)
    {
        continue;
    }

    foreach ($read as $client) {
        if ($client === $sock) {
            if (($client = socket_accept($sock)) === false) {
                echo socket_strerror(socket_last_error($sock)) . "\n";
                break;
            }
            $clients[] = $client;
            $welcome = "TCP Ping/Pong Server\r\n";
            socket_write($client, $welcome, strlen($welcome));
        } else {
            if (($buffer = @socket_read($client, $bufferSize, PHP_NORMAL_READ)) === false) {
                socket_close($client);
                $key = array_search($client, $clients, true);
                unset($clients[$key]);
                continue;
            }
            if (!$buffer = trim($buffer)) {
                continue;
            }
            if ($buffer === 'exit') {
                socket_shutdown($client);
                continue;
            }
            $talkback = "Received: '{$buffer}'\r\n";
            if (socket_write($client, $talkback, strlen($talkback)) === false) {
                socket_close($client);
                $key = array_search($client, $clients, true);
                unset($clients[$key]);
            }
        }
    }
} while (true);

socket_shutdown($sock);
socket_close($sock);
?>