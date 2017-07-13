<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Net\NetException;
use Lead\Net\Http\Request;
use Lead\Net\Http\Response;

use Kahlan\Plugin\Monkey;

describe("Response", function() {

    describe("->__construct()", function() {

        it("sets defaults values", function() {

            $response = new Response();
            expect($response->export())->toBe([
                'status'  => [200, 'OK'],
                'version' => '1.1',
                'headers' => $response->headers,
                'body'    => $response->stream()
            ]);

        });

        it("sets status", function() {

            $response = new Response(['status' => 404]);
            expect($response->status())->toEqual([404, 'Not Found']);

        });

        it("sets set-cookies", function() {

            $response = new Response([
                'cookies' => [
                    'foo' => 'bar',
                    'bar' => 'foo'
                ]
            ]);

            expect($response->headers->cookies->data())->toEqual([
                'foo' => [
                    [
                        'value'    => 'bar',
                        'expires'  => null,
                        'path'     => '/',
                        'domain'   => null,
                        'max-age'  => null,
                        'secure'   => false,
                        'httponly' => false
                    ]
                ],
                'bar' => [
                    [
                        'value'    => 'foo',
                        'expires'  => null,
                        'path'     => '/',
                        'domain'   => null,
                        'max-age'  => null,
                        'secure'   => false,
                        'httponly' => false
                    ]
                ]
            ]);

        });

    });

    describe("->status()", function() {

        it("sets the request's status using an integer", function() {

            $response = new Response();
            expect($response->status(404))->toBe($response);
            expect($response->status())->toEqual([404, 'Not Found']);

        });

        it("sets the request's status using an array", function() {

            $response = new Response();
            expect($response->status([404]))->toBe($response);
            expect($response->status())->toEqual([404, 'Not Found']);

        });

        it("sets the request's status using an array with a custom message definition", function() {

            $response = new Response();
            expect($response->status([404, 'Page Not Found']))->toBe($response);
            expect($response->status())->toEqual([404, 'Page Not Found']);

        });

    });

    describe("->cache()", function() {

        it("adds no-cache headers", function() {

            $response = new Response();
            $response->cache(false);

            $expected = <<<EOD
Expires: Mon, 26 Jul 1997 05:00:00 GMT\r
Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0\r
Pragma: no-cache\r
\r

EOD;

            expect($response->headers->to('header'))->toBe($expected);

        });

        it("adds cache headers to a specific date", function() {

            Monkey::patch('time', function() {
                return 1451001000;
            });

            $response = new Response();
            $response->cache(1451001600);

            $expected = <<<EOD
Expires: Fri, 25 Dec 2015 00:00:00 GMT\r
Cache-Control: max-age=600\r
Pragma: no-cache\r
\r

EOD;
            expect($response->headers->to('header'))->toBe($expected);

        });

    });

    describe("->digest()", function() {

        it("parses digest header", function() {

            $headers = [
                'WWW-Authenticate: Digest realm="app",qop="auth",nonce="4ee1617b8756e",opaque="dd7bcee161192cb8fba765eb595eba87"'
            ];
            $response = new Response(['headers' => $headers]);

            $result = array_filter($response->digest());
            expect($result)->toEqual([
                'realm' => 'app',
                'qop' => 'auth',
                'nonce' => '4ee1617b8756e',
                'opaque' => 'dd7bcee161192cb8fba765eb595eba87'
            ]);

        });

        it("returns and empty array when there's no WWW-Authenticate header", function() {

            $response = new Response();
            expect($response->digest())->toBe([]);

        });

    });

    context("through ->headers()", function() {

        it("sets Set-Cookie values", function() {

            $response = new Response();
            $response->headers['Set-Cookie'] = 'foo1=bar1; Path=/';
            $response->headers['Set-Cookie'] = 'foo2=bar2; Path=/';
            $response->headers['Set-Cookie'] = 'foo3=bar3; Path=/';


            $expected =<<<EOD
Set-Cookie: foo1=bar1; Path=/\r
Set-Cookie: foo2=bar2; Path=/\r
Set-Cookie: foo3=bar3; Path=/\r
\r

EOD;

            expect($response->headers->to('header'))->toBe($expected);

        });

    });

    describe("->negotiate()", function() {

        it("negotiates a format from a request", function() {

            $request = new Request();
            $response = new Response();

            $request->headers['Accept'] = "text/html;q=0.2,application/json,application/xml;q=0.9,*/*;q=0.8";
            $response->negotiate($request);
            expect($response->format())->toBe('json');

            $request->headers['Accept'] = "text/html,application/json;q=0.2,application/xml;q=0.9,*/*;q=0.8";
            $response->negotiate($request);
            expect($response->format())->toBe('html');

        });

        it("throws an exception if the response format can't be negotiated", function() {

            $closure = function() {
                $request = new Request();
                $response = new Response();

                $request->headers['Accept'] = "application/vnd.api+json";
                $response->negotiate($request);
            };

            expect($closure)->toThrow(new NetException('Unsupported Media Type: ["application/vnd.api+json"].', 415));

        });

    });

    describe("->toMessage()", function() {

        it("casts the response as a string", function() {

            $response = new Response([
                'format' => 'json',
                'data'   => ['hello' => 'world']
            ]);
            $cookies = $response->headers->cookies;

            $cookies['foo'] = 'bar';
            $cookies['bin'] = 'baz';
            $cookies['foo'] = ['value' => 'bin', 'path' => '/foo'];

            $expected = <<<EOD
HTTP/1.1 200 OK\r
Content-Type: application/json\r
Content-Length: 17\r
Set-Cookie: foo=bar; Path=/\r
Set-Cookie: bin=baz; Path=/\r
Set-Cookie: foo=bin; Path=/foo\r
\r
{"hello":"world"}
EOD;
            expect($response->toMessage())->toBe($expected);

        });

    });

    describe("->toString()", function() {

        it("casts the response as a string", function() {

            $response = new Response([
                'format' => 'json',
                'data'   => ['hello' => 'world']
            ]);
            $cookies = $response->headers->cookies;

            $cookies['foo'] = 'bar';
            $cookies['bin'] = 'baz';
            $cookies['foo'] = ['value' => 'bin', 'path' => '/foo'];

            expect($response->toString())->toBe('{"hello":"world"}');

        });

    });

    describe("->__toString()", function() {

        it("echoes the response and populates headers", function() {

            $headers = [];

            Monkey::patch('header', function() use (&$headers) {
                $headers[] = func_get_args();
            });

            $closure = function() {
                $response = new Response([
                    'format' => 'json',
                    'data'   => ['hello' => 'world']
                ]);
                $response->dump();
            };

            expect($closure)->toEcho('{"hello":"world"}');
            expect($headers)->toEqual([
                ["HTTP/1.1 200 OK"],
                ["Content-Type: application/json"],
                ["Content-Length: 17"]
            ]);

        });

        it("echoes the response, populates headers and process the echoing as chunk", function() {

            $headers = [];

            Monkey::patch('header', function() use (&$headers) {
                $headers[] = func_get_args();
            });

            $closure = function() {
                $response = new Response([
                    'format' => 'json'
                ]);
                $response->headers['Transfer-Encoding'] = 'chunked';
                $response->dump();
                $response->push(['hello' => 'world']);
                $response->end();
            };

            expect($closure)->toEcho("11\r\n{\"hello\":\"world\"}\r\n0\r\n\r\n");
            expect($headers)->toEqual([
                ["HTTP/1.1 200 OK"],
                ["Content-Type: application/json"],
                ["Transfer-Encoding: chunked"]
            ]);

        });

    });

    describe("->__clone", function() {

        it("clones the headers and the stream resource", function() {

            $response = new Response(['body' => 'Body Message']);
            $new = clone $response;
            expect($response->headers)->not->toBe($new->headers);
            expect($response->stream())->not->toBe($new->stream());

        });

        it("clones cookies", function() {

            $response = new Response(['body' => 'Body Message']);
            $cookies = $response->headers->cookies;
            $cookies['foo'] = 'bar';

            $newRequest = clone $response;
            $new = $newRequest->headers->cookies;
            expect($cookies['foo'][0])->not->toBe($new['foo'][0]);
            expect($cookies['foo'][0]->value())->toBe($new['foo'][0]->value());

        });

    });

    describe("::parse()", function() {

        it("creates a response with some set-cookies", function() {

            Monkey::patch('time', function() {
                return strtotime('24 Dec 2015');
            });

            $message = join("\r\n", [
                'HTTP/1.1 200 OK',
                'Connection: close',
                'Content-Type: text/plain;charset=UTF8',
                'Content-Length: 5',
                'Set-Cookie: doctor=who; Path=/tardis; HttpOnly',
                'Set-Cookie: test=foo%20bar; Expires=Fri, 25 Dec 2015 00:00:00 GMT; Secure',
                'Set-Cookie: test=foo%2Bbin; Path=/test; Domain=.domain.com',
                '',
                'Test!'
            ]);
            $cookies = [
                'doctor' => [
                    [
                        'value'    => 'who',
                        'expires'  => null,
                        'path'     => '/tardis',
                        'domain'   => null,
                        'max-age'  => null,
                        'secure'   => false,
                        'httponly' => true
                    ]
                ],
                'test' => [
                    [
                        'value'    => 'foo bar',
                        'expires'  => 1451001600,
                        'path'     => null,
                        'domain'   => null,
                        'max-age'  => null,
                        'secure'   => true,
                        'httponly' => false
                    ],
                    [
                        'value'    => 'foo+bin',
                        'expires'  => null,
                        'path'     => '/test',
                        'domain'   => '.domain.com',
                        'max-age'  => null,
                        'secure'   => false,
                        'httponly' => false
                    ]
                ]
            ];
            $response = Response::parse($message);
            expect($response->headers->cookies->data())->toBe($cookies);
            expect($response->toMessage())->toBe($message);

        });

        it("decodes chunked body", function() {

            $headers = join("\r\n", [
                'HTTP/1.1 200 OK',
                'Date: Mon, 22 Mar 2004 11:15:03 GMT',
                'Content-Type: text/html',
                'Transfer-Encoding: chunked',
                '',
                ''
            ]);

            $body  = "29\r\n";
            $body .= "<html><body><p>The file you requested is \r\n";
            $body .= "6\r\n";
            $body .= "3,400 \r\n";
            $body .= "22\r\n";
            $body .= "bytes long and was last modified: \r\n";
            $body .= "1d\r\n";
            $body .= "Fri, 25 Dec 2015 00:00:00 GMT\r\n";
            $body .= "13\r\n";
            $body .= ".</p></body></html>\r\n";
            $body .= "0\r\n\r\n";

            $response = Response::parse($headers . $body);

            $expected = <<<EOD
<html><body><p>The file you requested is 3,400 bytes long and was last modified: Fri, 25 Dec 2015 00:00:00 GMT.</p></body></html>
EOD;

            expect($response->body())->toBe($expected);


        });

        it("throws an exception if the message can't be parsed", function() {

            $closure = function() {
                Response::parse('');
            };

            expect($closure)->toThrow(new NetException('The CRLFCRLF separator between headers and body is missing.'));

        });

    });

});