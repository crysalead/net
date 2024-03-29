<?php
namespace Lead\Net\Spec\Suite\Http;

use RuntimeException;
use Lead\Net\NetException;
use Lead\Net\Http\Request;
use Lead\Net\Http\Response;
use Lead\Net\Http\Cookie\Cookie;
use Lead\Net\Http\Cookie\Cookies;

use Kahlan\Plugin\Monkey;

describe("Response", function() {

    describe("->__construct()", function() {

        it("sets defaults values", function() {

            $response = new Response();
            expect($response->export())->toBe([
                'status'  => [200, 'OK'],
                'version' => '1.1',
                'headers' => $response->headers(),
                'body'    => $response->stream()
            ]);

        });

        it("sets status", function() {

            $response = new Response(['status' => 404]);
            expect($response->status())->toEqual([404, 'Not Found']);

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

    describe("->locale()", function() {

        it("gets the locale", function() {

            $request = new Response(['locale' => 'en']);
            expect($request->locale())->toBe('en');

        });


        it("sets the locale if exists first", function() {

            $request = new Response(['locale' => 'en']);
            $request->locale('fr');
            expect($request->locale())->toBe('fr');

        });

    });

    describe("->cache()", function() {

        it("adds no-cache headers", function() {

            $response = new Response();
            $response->cache(false);

            $expected = <<<EOD
Expires: Mon, 26 Jul 1997 05:00:00 GMT\r
Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0\r
Pragma: no-cache
EOD;

            expect($response->headers()->to('header'))->toBe($expected);

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
Pragma: no-cache
EOD;
            expect($response->headers()->to('header'))->toBe($expected);

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

            $headers = $response->headers();
            $headers['Set-Cookie'][] = 'foo1=bar1; Path=/';
            $headers['Set-Cookie'][] = 'foo2=bar2; Path=/';
            $headers['Set-Cookie'][] = 'foo3=bar3; Path=/';


            $expected =<<<EOD
Set-Cookie: foo1=bar1; Path=/\r
Set-Cookie: foo2=bar2; Path=/\r
Set-Cookie: foo3=bar3; Path=/
EOD;

            expect($headers->to('header'))->toBe($expected);

        });

    });

    describe("->negotiate()", function() {

        it("negotiates a format from a request", function() {

            $request = new Request();
            $response = new Response();

            $headers = $request->headers();

            $headers['Accept'] = "text/html;q=0.2,application/json,application/xml;q=0.9,*/*;q=0.8";
            $response->negotiate($request);
            expect($response->format())->toBe('json');

            $headers['Accept'] = "text/html,application/json;q=0.2,application/xml;q=0.9,*/*;q=0.8";
            $response->negotiate($request);
            expect($response->format())->toBe('html');

        });

        it("throws an exception if the response format can't be negotiated", function() {

            $closure = function() {
                $request = new Request();
                $response = new Response();

                $headers = $request->headers();
                $headers['Accept'] = "application/vnd.api+json";
                $response->negotiate($request);
            };

            expect($closure)->toThrow(new NetException('Unsupported Media Type: `application/vnd.api+json`.', 415));

        });

    });

    describe("->applyCookies()", function() {

        it('generates a Set-Cookie HTTP headers', function() {

            $response = new Response();

            $cookies = new Cookies();
            $cookies['foo1'] = 'bar1';
            $cookies['foo2'] = 'bar2';
            $cookies['foo3'] = 'bar3';

            $response->applyCookies($cookies);
            $headers = $response->headers();

            expect($headers['Set-Cookie']->to('header'))->toBe(join("\r\n", [
                'Set-Cookie: foo1=bar1; Path=/',
                'Set-Cookie: foo2=bar2; Path=/',
                'Set-Cookie: foo3=bar3; Path=/'
            ]));

        });

        it("generates a custom Set-Cookie HTTP header", function() {

            $nextYear = date('Y') + 1;
            $expires = strtotime("{$nextYear}-12-25 00:00:00 UTC");

            $response = new Response();
            $cookie = new Cookie([
                'name'     => 'mycookie',
                'value'    => 'the cookie value',
                'expires'  => $expires,
                'path'     => '/blog',
                'domain'   => '.domain.com',
                'max-age'  => null,
                'secure'   => true,
                'httponly' => true
            ]);

            $date = gmdate('D, d M Y H:i:s \G\M\T', $expires);

            $response->applyCookies([$cookie]);
            $headers = $response->headers();

            $this->expect($headers['Set-Cookie']->to('header'))->toBe(
                "Set-Cookie: mycookie=the%20cookie%20value; Domain=.domain.com; Path=/blog; Expires={$date}; Secure; HttpOnly"
            );

        });

        it("generates a custom Set-Cookie HTTP header using Max-Age instead of Expires", function() {

            $response = new Response();
            $cookie = new Cookie([
                'name'     => 'mycookie',
                'value'    => 'the cookie value',
                'path'     => '/blog',
                'domain'   => '.domain.com',
                'max-age'  => '3600',
                'secure'   => true,
                'httponly' => true
            ]);

            $response->applyCookies([$cookie]);
            $headers = $response->headers();

            $this->expect($headers['Set-Cookie']->to('header'))->toBe(
                "Set-Cookie: mycookie=the%20cookie%20value; Domain=.domain.com; Path=/blog; Max-Age=3600; Secure; HttpOnly"
            );

        });

        it("ignores not setted values but Path", function() {

            $response = new Response();
            $cookie = new Cookie([
                'name'     => 'mycookie',
                'value'    => 'the cookie value'
            ]);

            $response->applyCookies([$cookie]);
            $headers = $response->headers();

            $this->expect($headers['Set-Cookie']->to('header'))->toBe(
                "Set-Cookie: mycookie=the%20cookie%20value; Path=/"
            );

        });

    });

    describe("->toMessage()", function() {

        it("casts the response as a string", function() {

            $response = new Response([
                'format' => 'json',
                'data'   => ['hello' => 'world']
            ]);

            $headers = $response->headers();
            $headers['Set-Cookie'][] = 'foo=bar; Path=/';
            $headers['Set-Cookie'][] = 'bin=baz; Path=/';
            $headers['Set-Cookie'][] = 'foo=bin; Path=/foo';

            $expected = <<<EOD
HTTP/1.1 200 OK\r
Content-Type: application/json\r
Set-Cookie: foo=bar; Path=/\r
Set-Cookie: bin=baz; Path=/\r
Set-Cookie: foo=bin; Path=/foo\r
Content-Length: 17\r
\r
{"hello":"world"}
EOD;
            expect($response->toMessage())->toBe($expected);

        });

        it("forces Content-Length to 0 for 204 resonse", function() {

            $response = new Response([
                'format' => 'json',
                'data'   => ['hello' => 'world']
            ]);

            $response->status(204);

            $expected = <<<EOD
HTTP/1.1 204 No Content\r
Content-Type: application/json\r
\r

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
                $responseHeaders = $response->headers();
                $responseHeaders['Transfer-Encoding'] = 'chunked';
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

        it("echoes a 204 response", function() {

            $headers = [];

            Monkey::patch('header', function() use (&$headers) {
                $headers[] = func_get_args();
            });

            $closure = function() {
                $response = new Response([
                    'format' => 'json',
                    'data'   => ['hello' => 'world']
                ]);
                $response->status(204);
                $response->dump();
            };

            expect($closure)->toEcho('');
            expect($headers)->toEqual([
                ["HTTP/1.1 204 No Content"],
                ["Content-Type: application/json"]
            ]);

        });

    });

    describe("->__clone", function() {

        it("clones the headers and the stream resource", function() {

            $response = new Response(['body' => 'Body Message']);
            $new = clone $response;
            expect($response->headers())->not->toBe($new->headers());
            expect($response->stream())->not->toBe($new->stream());

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
                'Content-Length: 5',
                'Set-Cookie: doctor=who; Path=/tardis; HttpOnly',
                'Set-Cookie: test=foo%20bar; Expires=Fri, 25 Dec 2015 00:00:00 GMT; Secure',
                'Set-Cookie: test=foo%2Bbin; Domain=.domain.com; Path=/test',
                'Content-Type: text/plain; charset=UTF-8',
                '',
                'Test!'
            ]);

            $response = Response::parse($message);

            expect($response->headers()->data())->toBe([
                'Connection: close',
                'Content-Length: 5',
                'Set-Cookie: doctor=who; Path=/tardis; HttpOnly',
                'Set-Cookie: test=foo%20bar; Expires=Fri, 25 Dec 2015 00:00:00 GMT; Secure',
                'Set-Cookie: test=foo%2Bbin; Domain=.domain.com; Path=/test',
                'Content-Type: text/plain; charset=UTF-8'
            ]);

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