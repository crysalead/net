<?php
namespace Lead\Net\Transport\Spec\Suite\Protocol\Http;

use Lead\Net\NetException;
use Lead\Net\Http\Request;
use Lead\Net\Http\Response;
use Lead\Net\Transport\Protocol\Http\Curl;

describe("Curl", function() {

    beforeEach(function() {
        $this->curl = new Curl();

        $this->url = function($path) {
            return 'http://localhost:10080/' . trim($path, '/');
        };

        $this->statusCodes = function() {
            return [
                200 => true,
                201 => true,
                202 => true,
                203 => true,
                204 => true,
                205 => true,
                206 => true,
                300 => false,
                301 => false,
                302 => false,
                303 => false,
                304 => false,
                305 => false,
                306 => false,
                307 => false,
                400 => false,
                401 => false,
                402 => false,
                403 => false,
                404 => false,
                405 => false,
                406 => false,
                407 => false,
                408 => false,
                409 => false,
                410 => false,
                411 => false,
                412 => false,
                413 => false,
                414 => false,
                415 => false,
                416 => false,
                417 => false,
                418 => false, // RFC 2324
                428 => false, // RFC 6585
                429 => false, // RFC 6585
                431 => false, // RFC 6585
                500 => false,
                501 => false,
                502 => false,
                503 => false,
                504 => false,
                505 => false,
                511 => false // RFC 6585
            ];
        };
    });

    context("with queries array", function() {

        describe("->send()", function() {

            context("with the dict protocol", function() {

                it("retreives some definition", function() {

                    $socket = $this->curl(['url' => 'dict://dict.org:2628/define:(hello):english:exact']);
                    $response = new Response();
                    $this->curl->send($socket, $response);
                    expect($response->toString())->toMatch('~An exclamation used as a greeting~');

                });

            });

            context("with the telnet protocol", function() {

                it("sends some data", function() {

                    $socket = $this->curl(['url' => 'telnet://localhost:10000', 'body' => "jaillet\r\nexit\r\n"]);
                    $response = new Response();
                    $this->curl->send($socket, $response);
                    expect((string) $response)->toBe("TCP Ping/Pong Server\r\nReceived: 'jaillet'\r\n");

                });

                xit("sends some data", function() {

                    $socket = $this->curl(['url' => 'telnet://localhost:23']);
                    $socket->on('received', function($data) {
                        $this->send("jaillet\r\n");
                    })->then(function($data) {
                    })->then(function($data) {
                        $this->send("the_lotus\r\n");
                    })->then(function($data) {
                    })->then(function($data) {
                        $this->send("exit\r\n");
                    });
                    $response = new Response();
                    $this->curl->send($socket, $response);
                });
            });

        });

        describe("->send()", function() {

            it("sends a get request", function() {

                $response = new Response();
                $this->curl->get(['url' => $this->url('/get')], $response);

                $result = json_decode($response->body(), true);

                expect($result['method'])->toBe('GET');
                expect($result['scheme'])->toBe('http');
                expect($result['version'])->toBe('1.1');
                expect($result['path'])->toBe('/get');
                expect($result['url'])->toBe($this->url('/get'));
                expect($result['args'])->toBe([]);

            });

            it("sends a put request", function() {

                $response = new Response();

                $this->curl->put([
                    'url'     => $this->url('put'),
                    'body'    => 'Hello World'
                ], $response);

                $result = json_decode($response->body(), true);

                expect($result['method'])->toBe('PUT');
                expect($result['scheme'])->toBe('http');
                expect($result['version'])->toBe('1.1');
                expect($result['path'])->toBe('/put');
                expect($result['url'])->toBe($this->url('/put'));
                expect($result['body'])->toBe('Hello World');
                expect($result['args'])->toBe([]);

            });

        });

    });

    context("with requests instance", function() {

        describe("->send()", function() {

            context("with the dict protocol", function() {

                it("retreives some definition", function() {

                    $socket = $this->curl([
                        'url' => 'dict://dict.org:2628/define:(hello):english:exact'
                    ]);
                    $response = new Response();
                    $this->curl->send($socket, $response);
                    expect($response->toString())->toMatch('~An exclamation used as a greeting~');

                });

            });

            context("with the telnet protocol", function() {

                it("sends some raw data", function() {

                    $response = new Response();
                    $this->curl->send([
                        'url' => 'telnet://localhost:10000',
                        'body' => "jaillet\r\nexit\r\n"
                    ], $response);
                    expect((string) $response)->toBe("TCP Ping/Pong Server\r\nReceived: 'jaillet'\r\n");

                });
            });

            context("with HTTP protocol", function() {

                it("sends simple GET", function() {

                    $request = Request::create('GET', $this->url('/get'));
                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['url'])->toBe($this->url('/get'));

                });

                it("populates the content type", function() {

                    $request = Request::create('GET', $this->url('/xml'));
                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);
                    expect($response->format())->toBe('xml');

                });

                it("sends GET with params", function() {

                    $request = Request::create('GET', $this->url('/get?test=true&test2=test'));
                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['url'])->toBe($this->url('/get?test=true&test2=test'));
                    expect($result['args'])->toBe(['test' => 'true', 'test2' => 'test']);

                });

                it("sends GET with data", function() {

                    $request = Request::create('GET', $this->url('/get'), ['query' => ['test' => 'true', 'test2' => 'test']]);
                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['url'])->toBe($this->url('/get?test=true&test2=test'));
                    expect($result['args'])->toBe(['test' => 'true', 'test2' => 'test']);

                });

                it("sends GET with nested data", function() {

                    $query = [
                        'test' => 'true',
                        'test2' => [
                            'test3' => 'test',
                            'test4' => 'test-too',
                        ]
                    ];

                    $request = Request::create('GET', $this->url('/get'), ['query' => $query]);
                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['url'])->toBe($this->url('/get?test=true&test2%5Btest3%5D=test&test2%5Btest4%5D=test-too'));
                    expect($result['args'])->toBe($query);

                });

                it("sends GET with headers", function() {

                    $requestedAt = (string) time();

                    $request = Request::create('GET', $this->url('/get'), ['headers' => [
                        'Requested-At: ' . $requestedAt
                    ]]);
                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['headers'])->toContain('Requested-At: ' . $requestedAt);

                });

                it("sends GET with a cookie", function() {

                    $request = Request::create('GET', $this->url('/cookies'));
                    $headers = $request->headers();
                    $headers['Cookie'][] = 'sessionid=123';

                    $response = new Response();

                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['cookies'])->toBe([
                        'sessionid' => '123'
                    ]);

                });

                it("sends GET with basic auth", function() {

                    $request = Request::create('GET', $this->url('/basic-auth/user/passwd'), [
                        'username' => 'user',
                        'password' => 'passwd',
                        'auth'     => 'Basic'
                    ]);
                    $response = new Response();

                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['authenticated'])->toBe(true);

                });

                it("sends GET with digest auth", function() {

                    $request = Request::create('GET', $this->url('/digest-auth/realm/qop/user/passwd'), [
                        'username' => 'user',
                        'password' => 'passwd',
                        'auth'     => [
                            'realm' => 'realm',
                            'qop' => 'qop',
                            'nonce' => '4bca0fbca7bd0',
                            'opaque' => 'd3fb67a7aa4d887ec4bf83040a820a46'
                        ]
                    ]);
                    $response = new Response();

                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['authenticated'])->toBe(true);

                });

                it("gets chunked data", function() {

                    $request = Request::create('GET', $this->url('/stream/5'));

                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $body = '';
                    for ($i = 0; $i < 5; $i++) {
                        $body.= "Line number {$i}\n";
                    }

                    expect($response->body())->toBe($body);

                });

                it("sends HEAD request", function() {

                    $request = Request::create('HEAD', $this->url('/head'));

                    $response = new Response();
                    $this->curl->send($request, $response);
                    expect($response->statusCode())->toBe(200);
                    expect($response->body())->toBe('');

                });

                foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {

                    $action = strtolower($method);

                    it("sends raw {$method}", function() use ($action) {

                        $request = Request::create($action, $this->url("/{$action}"), [
                            'body' => 'test'
                        ]);

                        $response = new Response();
                        $this->curl->send($request, $response);
                        expect($response->statusCode())->toBe(200);

                        $result = json_decode($response->body(), true);
                        expect($result['body'])->toBe('test');

                    });

                    it("sends x-www-form-urlencoded {$method}", function() use ($action) {

                        $request = Request::create($action, $this->url("/{$action}"), [
                            'headers' => [
                                'Content-Type: application/x-www-form-urlencoded'
                            ],
                            'body' => 'test=true&test2=test'
                        ]);

                        $response = new Response();
                        $this->curl->send($request, $response);

                        expect($response->statusCode())->toBe(200);

                        $result = json_decode($response->body(), true);
                        expect($result['form'])->toBe([
                            'test'  => 'true',
                            'test2' => 'test'
                        ]);
                        expect($result['body'])->toBe('test=true&test2=test');

                    });

                    it("sends x-www-form-urlencoded {$method} using data", function() use ($action) {

                        $request = Request::create($action, $this->url("/{$action}"), [
                            'headers' => [
                                'Content-Type: application/x-www-form-urlencoded'
                            ],
                            'data' => [
                                'test'  => 'true',
                                'test2' => 'test'
                            ]
                        ]);

                        $response = new Response();
                        $this->curl->send($request, $response);

                        expect($response->statusCode())->toBe(200);

                        $result = json_decode($response->body(), true);
                        expect($result['form'])->toBe([
                            'test'  => 'true',
                            'test2' => 'test'
                        ]);
                        expect($result['body'])->toBe('test=true&test2=test');

                    });

                }

                it("manages redirects by default", function() {

                    $request = Request::create('GET', $this->url('/redirect/6'));

                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['url'])->toBe($this->url('/get'));

                });

                it("manages relative redirects by default", function() {

                    $request = Request::create('GET', $this->url('/relative-redirect/6'));

                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['url'])->toBe($this->url('/get'));

                });

                it("throws on too many redirects", function() {

                    $closure = function() {
                        $request = Request::create('GET', $this->url('/redirect/11'));

                        $response = new Response();
                        $this->curl->send($request, $response, ['maxRedirects' => 10]);
                    };

                    expect($closure)->toThrow(new NetException('Number of redirects hit maximum amount', 47));

                });

                it("manages status code", function() {

                    foreach ($this->statusCodes() as $code => $success) {
                        $request = Request::create('GET', $this->url("/status/{$code}"));

                        $response = new Response();
                        $this->curl->send($request, $response, ['maxRedirects' => 0]);
                        expect($response->statusCode())->toBe($code);
                        expect($response->success())->toBe($success);
                    }

                });

                it("manages unknown status code", function() {

                    $request = Request::create('GET', $this->url("/status/599"));

                    $response = new Response();
                    $this->curl->send($request, $response, ['maxRedirects' => 0]);
                    expect($response->statusCode())->toBe(599);
                    expect($response->success())->toBe(false);

                });

                it("manages gzipped response", function() {

                    $request = Request::create('GET', $this->url('/gzip'));
                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['gzipped'])->toBe(true);

                });

                it("manages deflated response", function() {

                    $request = Request::create('GET', $this->url('/deflate'));
                    $response = new Response();
                    $this->curl->send($request, $response);

                    expect($response->statusCode())->toBe(200);

                    $result = json_decode($response->body(), true);

                    expect($result['deflated'])->toBe(true);

                });

                it("streams to file", function() {

                    $request = Request::create('GET', $this->url('/get'));
                    $filename = tempnam(sys_get_temp_dir(), 'dump');
                    $response = new Response(['body' => fopen($filename, 'r+')]);
                    $this->curl->send($request, $response);
                    expect($response->statusCode())->toBe(200);

                    $result = json_decode(file_get_contents($filename), true);
                    expect($result['url'])->toBe($this->url('/get'));

                    unlink($filename);

                });

                it("supports parallel queries", function() {

                    $request1 = Request::create('GET', $this->url('/get?name1=value1'));
                    $response1 = new Response();

                    $request2 = Request::create('GET', $this->url('/get?name2=value2'));
                    $response2 = new Response();

                    $this->curl->push($request1, $response1);
                    $this->curl->push($request2, $response2);

                    $this->curl->flush();

                    $result = json_decode($response1->body(), true);
                    expect($result['url'])->toBe($this->url('/get?name1=value1'));

                    // test2
                    $result = json_decode($response2->body(), true);
                    expect($result['url'])->toBe($this->url('/get?name2=value2'));

                });

                it("throws if SSH certificates are not OK", function() {

                    $closure = function() {
                        $request = Request::create('GET', 'https://testssl-revoked.disig.sk/');
                        $response = new Response();

                        $this->curl->send($request, $response);
                    };

                    expect($closure)->toThrow('SSL peer certificate or SSH remote key was not OK');

                });

                it("bypasses the expired SSH certificate", function() {

                    $closure = function() {
                        $request = Request::create('GET', 'https://testssl-revoked.disig.sk/');
                        $response = new Response();

                        $this->curl->send($request, $response, ['verifyHost' => 0]);
                    };

                    expect($closure)->not->toThrow('SSL peer certificate or SSH remote key was not OK');

                });

                it("throws when a timeout is reached", function() {

                    $closure = function() {
                        $request = Request::create('GET', $this->url('/delay/5'));
                        $response = new Response();
                        $this->curl->send($request, $response, ['timeout' => 1]);
                    };

                    expect($closure)->toThrow(new NetException('Timeout was reached', 28));

                });

            });

        });

    });

});
