<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Net\NetException;
use Lead\Net\Http\Request;
use Kahlan\Plugin\Stub;

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

    describe("->scheme()", function() {

        it("gets the scheme", function() {

            $request = new Request(['scheme' => 'https']);
            expect($request->scheme())->toBe('https');

        });

        it("sets the scheme", function() {

            $request = new Request();
            $request->scheme('https');

            expect($request->scheme())->toBe('https');

        });

    });

    describe("->port()", function() {

        it("gets the port", function() {

            $request = new Request(['port' => 8888]);
            expect($request->port())->toBe(8888);

        });

        it("returns the default scheme port if port is `null`", function() {

            $request = new Request(['scheme' => 'https']);
            expect($request->port())->toBe(443);

        });

        it("returns `80` for default scheme", function() {

            $request = new Request();
            expect($request->port())->toBe(80);

        });

        it("sets the port", function() {

            $request = new Request();
            $request->port(8888);

            expect($request->port())->toBe(8888);

        });

    });

    describe("->method()", function() {

        it("gets/sets the method", function() {

            $request = new Request();
            expect($request->method())->toBe('GET');
            expect($request->method('post'))->toBe($request);
            expect($request->method())->toBe('POST');

        });
    });

    describe("->path()", function() {

        it("gets the path", function() {

            $request = new Request(['path' => 'home/index.php']);
            expect($request->path())->toBe('/home/index.php');

        });

        it("sets the path", function() {

            $request = new Request();
            $request->path('/home/index.php');

            expect($request->path())->toBe('/home/index.php');

        });

        it("auto adds a leading slash", function() {

            $request = new Request();
            $request->path('home/index.php');

            expect($request->path())->toBe('/home/index.php');

        });

    });

    describe("->username()", function() {

        it("gets the username", function() {

            $request = new Request(['username' => 'username']);
            expect($request->username())->toBe('username');

        });

        it("sets the username", function() {

            $request = new Request();
            $request->username('username');

            expect($request->username())->toBe('username');

        });

    });

    describe("->password()", function() {

        it("gets the password", function() {

            $request = new Request(['password' => 'password']);
            expect($request->password())->toBe('password');

        });

        it("sets the password", function() {

            $request = new Request();
            $request->password('password');

            expect($request->password())->toBe('password');

        });

    });

    describe("->url()", function() {

        it("gets the url", function() {

            $request = new Request([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 80,
                'username' => 'username',
                'password' => 'password',
                'path'     => 'index.php'
            ]);
            expect($request->url())->toBe('http://username:password@www.domain.com/index.php');

        });

        it("gets the url with a custom port", function() {

            $request = new Request([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 8080,
                'username' => 'username',
                'password' => 'password',
                'path'     => 'index.php'
            ]);
            expect($request->url())->toBe('http://username:password@www.domain.com:8080/index.php');

        });

    });


    describe("->query()", function() {

        it("gets/sets the query string", function() {

            $expected = "?param=value&param1=value1";
            $request = new Request();
            expect($request->query(['param' => 'value', 'param1' => 'value1']))->toBe($request);
            expect($request->query())->toBe(['param' => 'value', 'param1' => 'value1']);

            expect($request->fullPath())->toBe('/?param=value&param1=value1');

        });

    });

    describe("->auth()", function() {

        it("sets a basic Authorization header", function() {

            $request = new Request([
                'username' => 'Willy',
                'password' => 'Boy',
                'auth'     => 'Basic'
            ]);
            expect((string) $request->headers()['Authorization'])->toBe('Authorization: Basic V2lsbHk6Qm95');

        });

        it("sets a digest Authorization header", function() {

            $request = new Request([
                'username' => 'Willy',
                'password' => 'Boy',
                'auth'     => [
                    'realm' => 'app',
                    'qop' => 'auth',
                    'nonce' => '4bca0fbca7bd0',
                    'opaque' => 'd3fb67a7aa4d887ec4bf83040a820a46'
                ]
            ]);

            expect((string) $request->headers()['Authorization'])->toMatch('~^Authorization: Digest~');

        });

        it("removes a setted Authorization", function() {

            $request = new Request([
                'username' => 'Willy',
                'password' => 'Boy',
                'auth'     => 'Basic'
            ]);
            expect((string) $request->headers()['Authorization'])->toBe('Authorization: Basic V2lsbHk6Qm95');

            $request->auth(false);
            expect(isset($request->headers()['Authorization']))->toBe(false);

        });

    });

    describe("->toString()", function() {

        it("adds the Content-Length for POST request", function() {

            $request = new Request([
                'method'  => 'POST',
                'type'    => 'application/x-www-form-urlencoded',
                'body'    => 'name1=value1&name2=value2'
            ]);

            $expected =<<<EOD
POST / HTTP/1.1\r
Host: localhost\r
Content-Type: application/x-www-form-urlencoded\r
Connection: Close\r
User-Agent: Mozilla/5.0\r
Content-Length: 25\r
\r
name1=value1&name2=value2
EOD;

            expect($request->toString())->toBe($expected);

        });

        it("throws an exception when Content-Length is required but not be determined", function() {

            $closure = function() {
                $stream = Stub::create(['extends' => 'Lead\Storage\Stream\Stream']);
                Stub::on($stream)->method('length')->andReturn(null);

                $request = new Request([
                    'method' => 'POST',
                    'type'   => 'application/x-www-form-urlencoded',
                    'body'   => $stream
                ]);
                $request->toString();
            };


            expect($closure)->toThrow(new NetException('A Content-Length header is required but the request stream has a `null` length.'));

        });

    });

    describe("->__toString()", function() {

        it("casts to a string", function() {

            $headers = [
                'Date: Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type: text/html; charset=UTF-8',
                'Vary: Accept-Encoding, Cookie, User-Agent',
            ];

            $request = new Request([
                'headers' => $headers,
                'body'    => 'Body Message'
            ]);


            $expected =<<<EOD
GET / HTTP/1.1\r
Host: localhost\r
Date: Thu, 25 Dec 2014 00:00:00 GMT\r
Content-Type: text/html; charset=UTF-8\r
Vary: Accept-Encoding, Cookie, User-Agent\r
Connection: Close\r
User-Agent: Mozilla/5.0\r
\r
Body Message
EOD;

            expect((string) $request)->toBe($expected);

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