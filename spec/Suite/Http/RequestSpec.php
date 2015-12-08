<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Net\Http\Request;

describe("Request", function() {

    describe("->__construct()", function() {

        it("sets default host header", function() {

            $request = new Request(['host' => 'www.domain.com', 'port'  => 80]);
            expect((string) $request->headers()['Host'])->toBe('Host: www.domain.com');

        });

        it("sets non default port number on host header", function() {

            $request = new Request(['host' => 'www.domain.com', 'port'  => 90]);
            expect((string) $request->headers()['Host'])->toBe('Host: www.domain.com:90');

        });

    });

    describe("->to('array')", function() {

        it("exports default values", function() {

            $request = new Request();

            expect($request->to('array'))->toEqual([
                'method'   => 'GET',
                'scheme'   => 'http',
                'version'  => '1.1',
                'host'     => 'localhost',
                'port'     => 80,
                'path'     => '/',
                'query'    => '',
                'username' => null,
                'password' => null,
                'url'      => 'http://localhost/',
                'headers'  => [
                    'Host: localhost',
                    'Connection: Close',
                    'User-Agent: Mozilla/5.0'
                ],
                'stream'   => $request->stream()
            ]);

        });

        it("exports a request", function() {

            $request = new Request([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 80,
                'username' => 'username',
                'password' => 'password',
                'path'     => 'index.php'
            ]);

            expect($request->to('array'))->toEqual([
                'method'   => 'GET',
                'scheme'   => 'http',
                'version'  => '1.1',
                'host'     => 'www.domain.com',
                'port'     => 80,
                'path'     => '/index.php',
                'query'    => '',
                'username' => 'username',
                'password' => 'password',
                'url'      => 'http://username:password@www.domain.com/index.php',
                'headers'  => [
                    'Host: www.domain.com',
                    'Connection: Close',
                    'User-Agent: Mozilla/5.0'
                ],
                'stream'   => $request->stream()
            ]);

        });

    });

});