<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Net\NetException;
use Lead\Net\Http\Request;
use Lead\Net\Http\Cookie\Cookies;

use Kahlan\Plugin\Double;

describe("Request", function() {

    describe("->__construct()", function() {

        it("sets default host header", function() {

            $request = new Request(['host' => 'www.domain.com', 'port'  => 80]);
            $headers = $request->headers();
            expect((string) $headers['Host'])->toBe('Host: www.domain.com');

        });

        it("sets non default port number on host header", function() {

            $request = new Request(['host' => 'www.domain.com', 'port'  => 90]);
            $headers = $request->headers();
            expect((string) $headers['Host'])->toBe('Host: www.domain.com:90');

        });

    });

    describe("::create()", function() {

        it("parses absolute url", function() {

            $request = Request::create('GET', 'https://username:password@www.domain.com:8000/foo?bar=baz#quz');
            expect($request->export())->toEqual([
                'method'   => 'GET',
                'scheme'   => 'https',
                'version'  => '1.1',
                'hostname' => 'www.domain.com',
                'port'     => 8000,
                'path'     => '/foo',
                'query'    => '?bar=baz',
                'fragment' => 'quz',
                'username' => 'username',
                'password' => 'password',
                'url'      => 'https://www.domain.com:8000/foo?bar=baz#quz'
            ]);

        });

        it("throw an exception when the passed url is invalid", function() {

            $closure = function() {
                Request::create('GET', '/relative/url');
            };

            expect($closure)->toThrow(new NetException("Invalid url: `'/relative/url'`."));

        });

    });

    describe("->host()", function() {

        it("sets the host", function() {

            $request = new Request();
            $request->host('www.example.com:8000');

            expect($request->host())->toBe('www.example.com:8000');
            expect($request->hostname())->toBe('www.example.com');
            expect($request->port())->toBe('8000');

            $headers = $request->headers();
            expect((string) $headers['Host'])->toBe('Host: www.example.com:8000');

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
                'path'     => 'index.php'
            ]);
            expect($request->url())->toBe('http://www.domain.com/index.php');

        });

        it("gets the url with a custom port", function() {

            $request = new Request([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 8080,
                'path'     => 'index.php'
            ]);
            expect($request->url())->toBe('http://www.domain.com:8080/index.php');

        });

    });

    describe("->mode()", function() {

        it("sets the absolute request mode", function() {

            $request = new Request([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'path'     => 'index.php'
            ]);
            expect($request->line())->toBe('GET /index.php HTTP/1.1');

            $request->mode('absolute');
            expect($request->line())->toBe('GET http://www.domain.com/index.php HTTP/1.1');

        });

        it("sets the asterisk mode", function() {

            $request = new Request([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'path'     => 'index.php'
            ]);
            expect($request->line())->toBe('GET /index.php HTTP/1.1');

            $request->method('OPTIONS');
            $request->mode('asterisk');
            expect($request->line())->toBe('OPTIONS * HTTP/1.1');

        });

    });

    describe("->requestTarget()", function() {

        it("sets the request mode", function() {

            $request = new Request([
                'method'   => 'CONNECT',
                'host'     => 'www.domain.com',
                'username' => 'username',
                'password' => 'password',
                'path'     => 'index.php'
            ]);
            expect($request->line())->toBe('CONNECT username:password@www.domain.com HTTP/1.1');

            $request->port(8000);
            expect($request->line())->toBe('CONNECT username:password@www.domain.com:8000 HTTP/1.1');

        });

    });

    describe("->query()", function() {

        it("gets/sets the query string", function() {

            $expected = "?param=value&param1=value1";
            $request = new Request();
            expect($request->query(['param' => 'value', 'param1' => 'value1']))->toBe($request);
            expect($request->query())->toBe(['param' => 'value', 'param1' => 'value1']);

            expect($request->requestTarget())->toBe('/?param=value&param1=value1');

        });

    });

    describe("->auth()", function() {

        it("sets a basic Authorization header", function() {

            $request = new Request([
                'username' => 'Willy',
                'password' => 'Boy',
                'auth'     => 'Basic'
            ]);

            $headers = $request->headers();
            expect((string) $headers['Authorization'])->toBe('Authorization: Basic V2lsbHk6Qm95');

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

            $headers = $request->headers();
            expect((string) $headers['Authorization'])->toMatch('~^Authorization: Digest~');

        });

        it("removes a setted Authorization", function() {

            $request = new Request([
                'username' => 'Willy',
                'password' => 'Boy',
                'auth'     => 'Basic'
            ]);

            $headers = $request->headers();
            expect((string) $headers['Authorization'])->toBe('Authorization: Basic V2lsbHk6Qm95');

            $request->auth(false);
            expect(isset($headers['Authorization']))->toBe(false);

        });

    });

    describe("->applyCookies()", function() {

        beforeEach(function() {

            $this->cookies = new Cookies();
            $this->cookies['foo1'] = ['value' => 'bar1', 'path' => '/home', 'domain' => '.domain.com'];
            $this->cookies['foo1'] = ['value' => 'bar11', 'path' => '/home/index', 'domain' => '.domain.com'];
            $this->cookies['foo2'] = ['value' => 'bar2', 'path' => '/', 'domain' => '.domain.com'];

        });

        it('generates the Cookie header', function() {

            $request = new Request(['host' => 'www.domain.com']);
            $request->applyCookies($this->cookies);

            $headers = $request->headers();
            expect($headers['Cookie']->value())->toBe('foo2=bar2');

        });

        it('orders most specific cookie first', function() {

            $request = new Request([
                'host' => 'www.domain.com',
                'path' => '/home/index'
            ]);

            $request->applyCookies($this->cookies);

            $headers = $request->headers();
            expect($headers['Cookie']->value())->toBe('foo1=bar11; foo1=bar1; foo2=bar2');

        });

    });

    describe("->toMessage()", function() {

        it("casts the instance to a string", function() {

            $headers = [
                'Date: Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type: text/html; charset=UTF-8',
                'Vary: Accept-Encoding, Cookie, User-Agent',
            ];

            $request = new Request([
                'headers' => $headers,
                'data'    => 'Body Message'
            ]);


            $expected =<<<EOD
GET / HTTP/1.1\r
Host: localhost\r
Connection: Close\r
User-Agent: Mozilla/5.0\r
Date: Thu, 25 Dec 2014 00:00:00 GMT\r
Vary: Accept-Encoding, Cookie, User-Agent\r
Content-Type: text/html; charset=UTF-8\r
\r
Body Message
EOD;

            expect($request->toMessage())->toBe($expected);

        });

        it("adds the Content-Length for POST request", function() {

            $request = new Request([
                'method'  => 'POST',
                'mime'    => 'application/x-www-form-urlencoded',
                'data'    => ['name1' => 'value1', 'name2' => 'value2']
            ]);

            $expected =<<<EOD
POST / HTTP/1.1\r
Host: localhost\r
Connection: Close\r
User-Agent: Mozilla/5.0\r
Content-Type: application/x-www-form-urlencoded\r
Content-Length: 25\r
\r
name1=value1&name2=value2
EOD;

            expect($request->toMessage())->toBe($expected);

        });

        it("generates chunks when the Transfer-Encoding header is setted to chunked", function() {

            $request = new Request([
                'method'  => 'POST',
                'headers' => [
                    'Transfer-Encoding: chunked'
                ],
                'mime'    => 'application/x-www-form-urlencoded',
                'data'    => ['name1' => 'value1', 'name2' => 'value2']
            ]);

            $expected =<<<EOD
POST / HTTP/1.1\r
Host: localhost\r
Connection: Close\r
User-Agent: Mozilla/5.0\r
Transfer-Encoding: chunked\r
Content-Type: application/x-www-form-urlencoded\r
\r
19\r
name1=value1&name2=value2\r
0\r
\r

EOD;

            expect($request->toMessage())->toBe($expected);

        });

        it("throws an exception when Content-Length is required but not be determined", function() {

            $closure = function() {
                $stream = Double::instance(['extends' => 'Lead\Net\Part']);
                allow($stream)->toReceive('length')->andReturn(null);

                $request = new Request([
                    'method' => 'POST',
                    'mime'   => 'application/x-www-form-urlencoded',
                    'body'   => $stream
                ]);
                $request->toMessage();
            };


            expect($closure)->toThrow(new NetException('A Content-Length header is required but the request stream has a `null` length.'));

        });

    });

    describe("->export()", function() {

        it("exports default values", function() {

            $request = new Request();

            expect($request->export())->toEqual([
                'method'   => 'GET',
                'scheme'   => 'http',
                'version'  => '1.1',
                'hostname' => 'localhost',
                'port'     => 80,
                'path'     => '/',
                'query'    => '',
                'fragment' => '',
                'username' => null,
                'password' => null,
                'url'      => 'http://localhost/'
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

            expect($request->export())->toEqual([
                'method'   => 'GET',
                'scheme'   => 'http',
                'version'  => '1.1',
                'hostname' => 'www.domain.com',
                'port'     => 80,
                'path'     => '/index.php',
                'query'    => '',
                'fragment' => '',
                'username' => 'username',
                'password' => 'password',
                'url'      => 'http://www.domain.com/index.php'
            ]);

        });

    });

    describe("->toString()", function() {

        it("casts to a string", function() {

            $headers = [
                'Date: Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type: text/html; charset=UTF-8',
                'Vary: Accept-Encoding, Cookie, User-Agent',
            ];

            $request = new Request([
                'headers' => $headers,
                'data'    => 'Body Message'
            ]);

            expect($request->toString())->toBe('Body Message');

        });

    });

    describe("->__clone", function() {

        it("clones the headers and the stream resource", function() {

            $request = new Request(['data' => 'Body Message']);
            $new = clone $request;
            expect($request->headers())->not->toBe($new->headers());
            expect($request->stream())->not->toBe($new->stream());

        });

    });

});