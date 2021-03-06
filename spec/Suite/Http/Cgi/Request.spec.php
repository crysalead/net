<?php
namespace Lead\Net\Spec\Suite\Http\Cgi;

use Lead\Dir\Dir;
use Lead\Set\Set;
use Lead\Net\NetException;
use Lead\Net\Http\Cgi\Request;

describe("Request", function() {

    beforeAll(function() {
        $this->globalNames =['_GET', '_POST', '_SERVER'];
        $this->oldEnv = [];
        foreach ($this->globalNames as $varname) {
            $this->oldEnv[$varname] = isset($GLOBALS[$varname]) ? $GLOBALS[$varname] : null;
            unset($GLOBALS[$varname]);
        }
    });

    afterEach(function() {
        foreach ($this->globalNames as $varname) {
            $GLOBALS[$varname] = $this->oldEnv[$varname];
        }
    });

    describe("__construct", function() {

        it("sets default values", function() {

            $request = new Request();

            expect($request->export())->toEqual([
                'basePath' => '',
                'form'     => [],
                'params'   => [],
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

            $expected = <<<EOD
Host: localhost\r
Connection: Close\r
User-Agent: Mozilla/5.0\r
Content-Type: text/html; charset=UTF-8
EOD;

            expect((string) $request->headers())->toBe($expected);
            expect((string) $request->stream())->toBe('');

        });

        it("uses `'CONTENT_LENGTH'` from environment variables if exists and not set", function() {

            $request = new Request([
                'env' => [
                    'CONTENT_LENGTH' => 50
                ]
            ]);
            $headers = $request->headers();
            expect($headers['Content-Length']->value())->toEqual(50);

        });

        it("adds all `'HTTP_*'` environment variables to header", function() {

            $request = new Request([
                'env' => [
                    'HTTP_ACCEPT'          => '*.*',
                    'HTTP_ACCEPT_CHARSET'  => 'iso-8859-1,*,utf-8',
                    'HTTP_ACCEPT_ENCODING' => 'gzip',
                    'HTTP_CONNECTION'      => 'Keep-Alive',
                    'HTTP_HOST'            => 'www.example.com',
                    'HTTP_REFERER'         => 'www.search-engine.com',
                    'HTTP_USER_AGENT'      => 'Mozilla/3.0'
                ]
            ]);

            $expected = <<<EOD
Host: www.example.com\r
Accept: *.*\r
Accept-Charset: iso-8859-1, *, utf-8\r
Accept-Encoding: gzip\r
Connection: Keep-Alive\r
Referer: www.search-engine.com\r
User-Agent: Mozilla/3.0\r
Content-Type: text/html; charset=UTF-8
EOD;

            expect((string) $request->headers())->toBe($expected);
            expect((string) $request->host())->toBe('www.example.com');

        });

        it("allows `form['_method']` to override `'REQUEST_METHOD'`", function() {

            $request = new Request(['form' => ['_method' => 'PATCH']]);

            expect($request->method())->toBe('PATCH');
            expect($request->env['REQUEST_METHOD'])->toBe('PATCH');

        });

        it("extracts username & password from Basic authorization", function() {

            $request = new Request(['env' => [
                'HTTP_AUTHORIZATION' => 'Basic d2lsbHk6YWJjZGVm'
            ]]);

            expect($request->username())->toBe('willy');
            expect($request->password())->toBe('abcdef');

            $headers = $request->headers();
            expect($headers['Authorization']->value())->toBe('Basic d2lsbHk6YWJjZGVm');

        });

        it("extracts username from Digest authorization", function() {

            $digest  = 'Digest qop="auth", nonce="4bca0fbca7bd0", ';
            $digest .= 'nc=00000001, cnonce="95b2cd1e179bf5414e52ed62811481cf", ';
            $digest .= 'uri="/http_auth", realm="app", ';
            $digest .= 'opaque="d3fb67a7aa4d887ec4bf83040a820a46", username="willy", ';
            $digest .= 'response="04d7d878c67f289f37e553d2025e3a52"';

            $request = new Request(['env' => [
                'HTTP_AUTHORIZATION' => $digest
            ]]);

            expect($request->username())->toBe('willy');
            expect($request->password())->toBe(null);

            $headers = $request->headers();
            expect($headers['Authorization']->value())->toBe($digest);

        });

        it("applies the ignore pattern to the base path", function() {

            $request = new Request([
                'basePath'   => '/base/path/webroot/index.php',
                'ignorePath' => '~/webroot/index.php$~'
            ]);

            expect($request->basePath())->toBe('/base/path');

        });

        it("overrides the default Content-Type a mime is provided", function() {

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

    });

    describe("->is()", function() {

        it("checks ssl", function() {

            $request = new Request(['env' => ['HTTPS' => 'off']]);
            expect($request->is('ssl'))->toBe(false);

            $request = new Request(['env' => ['HTTPS' => 'on']]);
            expect($request->is('ssl'))->toBe(true);

            $request = new Request(['env' => ['HTTPS' => null]]);
            expect($request->is('ssl'))->toBe(false);

        });

        it("checks format", function() {

            $request = new Request([
                'env' => [
                    'CONTENT_TYPE' => 'application/json; charset=UTF-8',
                    'REQUEST_METHOD' => 'POST'
                ]
            ]);
            expect($request->is('json'))->toBe(true);
            expect($request->is('html'))->toBe(false);
            expect($request->is('foo'))->toBe(false);

        });

        it("checks mobile", function() {

            $iPhone = 'Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like ';
            $iPhone .= 'Gecko) Version/3.0 Mobile/1A535b Safari/419.3';
            $request = new Request(['env' => ['HTTP_USER_AGENT' => $iPhone]]);
            expect($request->is('mobile'))->toBe(true);

            $android = 'Mozilla/5.0 (Linux; U; Android 0.5; en-us) AppleWebKit/522+ (KHTML, like ';
            $android .= 'Gecko) Safari/419.3';
            $request = new Request(['env' => ['HTTP_USER_AGENT' => $android]]);
            expect($request->is('mobile'))->toBe(true);

        });

    });

    describe("->detect()", function() {

        it("detects from rules", function() {

            $request = new Request([
                'env' => ['SOME_COOL_DETECTION' => true]
            ]);
            $request->detect('cool', ['env:SOME_COOL_DETECTION' => true]);

            expect($request->is('cool'))->toBe(true);
            expect($request->is('foo'))->toBe(false);

        });

        it("detects with regexp based rules", function() {
            $request = new Request([
                'env' => [
                    'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; U; XXXXX like Mac OS X; en) AppleWebKit/420+'
                ]
            ]);
            $request->detect('iPhone', ['http:user-agent' => '~iPhone~']);
            expect($request->is('iPhone'))->toBe(true);

        });

        it("detects with closure rules", function() {

            $request = new Request();
            $request->detect('cool', function ($request) { return true; });
            $request->detect('notCool', function ($request) { return false; });

            expect($request->is('cool'))->toBe(true);
            expect($request->is('notCool'))->toBe(false);

        });

        it("allows array definition", function() {
            $request = new Request();
            $request->detect([
                'cool' => function ($request) {
                    return true;
                }
            ]);
            expect($request->is('cool'))->toBe(true);
        });

    });

    describe("->basePath()", function() {

        it("sets the base path", function() {

            $request = new Request();
            expect($request->basePath())->toBe('');

            $request->basePath('/base/path');
            expect($request->basePath())->toBe('/base/path');

        });

        it("trims ending slash", function() {

            $request = new Request();

            $request->basePath('/base/path/');
            expect($request->basePath())->toBe('/base/path');

            $request->basePath('/');
            expect($request->basePath())->toBe('');

        });

        it("adds leading slash", function() {

            $request = new Request();

            $request->basePath('base/path/');
            expect($request->basePath())->toBe('/base/path');

        });

        it("lazily applies a base path", function() {

            $request = new Request([
                'ignorePath' => '~/webroot$~'
            ]);

            expect($request->basePath())->toBe('');

            $request->basePath('/base/path/webroot');
            expect($request->basePath())->toBe('/base/path');

        });

    });

    describe("->ignorePath()", function() {

        it("sets the ignore pattern", function() {

            $request = new Request();
            expect($request->ignorePath())->toBe(null);

            $request->ignorePath('~/webroot$~');
            expect($request->ignorePath())->toBe('~/webroot$~');

        });

        it("lazily applies the ignore pattern on the base path", function() {

            $request = new Request([
                'basePath' => '/base/path/webroot'
            ]);

            expect($request->basePath())->toBe('/base/path/webroot');

            $request->ignorePath('~/webroot$~');
            expect($request->basePath())->toBe('/base/path');

            $request->ignorePath(null);
            expect($request->basePath())->toBe('/base/path/webroot');

        });

    });

    describe("::ingoing()", function() {

        beforeEach(function() {
            defineGlobals();
        });

        it("creates a request from globals", function() {

            $request = Request::ingoing();

            expect($request->export())->toEqual([
                'basePath' => '/base/path/webroot',
                'form'     => [],
                'params'   => [],
                'method'   => 'GET',
                'scheme'   => 'http',
                'version'  => '1.1',
                'hostname' => 'localhost',
                'port'     => 80,
                'path'     => '/app',
                'query'    => '?get=value',
                'fragment' => '',
                'username' => null,
                'password' => null,
                'url'      => 'http://localhost/app?get=value'
            ]);

        });

        it("creates application/x-www-form-urlencoded request from globals", function() {

            defineGlobals([
                'SERVER' => [
                    'REQUEST_METHOD' => 'POST',
                    'CONTENT_TYPE'   => 'application/x-www-form-urlencoded'
                ]
            ]);

            allow('fopen')->toBeCalled()->andRun(function($filename, $mode) {
                if ($filename === 'php://input') {
                    $stream = fopen('php://temp', 'r+');
                    fwrite($stream, 'post=value');
                    rewind($stream);
                    return $stream;
                }
                return fopen($filename, $mode);
            });

            $request = Request::ingoing();

            expect($request->export())->toEqual([
                'basePath' => '/base/path/webroot',
                'form'     => $_POST,
                'params'   => [],
                'method'   => 'POST',
                'scheme'   => 'http',
                'version'  => '1.1',
                'hostname' => 'localhost',
                'port'     => 80,
                'path'     => '/app',
                'query'    => '?get=value',
                'fragment' => '',
                'username' => null,
                'password' => null,
                'url'      => 'http://localhost/app?get=value'
            ]);

        });

        it("supports url rewriting", function() {

            defineGlobals([
                'SERVER' => [
                    'REQUEST_URI' => '/base/path/app?get=value'
                ]
            ]);

            $request = Request::ingoing();

            expect($request->export())->toEqual([
                'basePath' => '/base/path',
                'form'     => [],
                'params'   => [],
                'method'   => 'GET',
                'scheme'   => 'http',
                'version'  => '1.1',
                'hostname' => 'localhost',
                'port'     => 80,
                'path'     => '/app',
                'query'    => '?get=value',
                'fragment' => '',
                'username' => null,
                'password' => null,
                'url'      => 'http://localhost/app?get=value'
            ]);

        });

        it("uses php://input as body message by default", function() {

            $temp = Dir::tempnam(sys_get_temp_dir(), 'spec');
            $filename = tempnam($temp, 'foo');

            $handler = fopen($filename, 'w');
            fwrite($handler, 'Hello World');
            fclose($handler);

            allow('fopen')->toBeCalled()->andRun(function($name, $mode,  $use_include_path = false) use ($filename) {
                if ($name === 'php://input') {
                    $name = $filename;
                }
                return fopen($name, $mode,  $use_include_path);
            });

            $request = Request::ingoing();
            expect($request->body())->toBe('Hello World');


            Dir::remove($temp, ['recursive' => true]);

        });

        it("supports URL rewrite", function() {

            $_SERVER['REQUEST_URI'] = '/base/path/webroot/controller/action';
            $_SERVER['SCRIPT_NAME'] = '/base/path/webroot/index.php';

            $request = Request::ingoing();

            expect($request->basePath())->toBe('/base/path/webroot');
            expect($request->path())->toBe('/controller/action');

        });

        it("supports URL rewrite with common prefix", function() {

            $_SERVER['REQUEST_URI'] = '/base/path/webroot/index/view/1';
            $_SERVER['SCRIPT_NAME'] = '/base/path/webroot/index.php';

            $request = Request::ingoing();

            expect($request->basePath())->toBe('/base/path/webroot');
            expect($request->path())->toBe('/index/view/1');

        });

        it("supports URL rewrite using the index.php notation", function() {

            $_SERVER['REQUEST_URI'] = '/base/path/webroot/index.php/image/edit/1';
            $_SERVER['SCRIPT_NAME'] = '/base/path/webroot/index.php';

            $request = Request::ingoing();

            expect($request->basePath())->toBe('/base/path/webroot');
            expect($request->path())->toBe('/image/edit/1');

        });

        it("supports PHP build-in server SCRIPT_NAME", function() {

            $_SERVER['REQUEST_URI'] = '/controller/action';
            $_SERVER['SCRIPT_NAME'] = '/controller/action';

            $request = Request::ingoing();

            expect($request->basePath())->toBe('');
            expect($request->path())->toBe('/controller/action');

        });

        it("supports custom BASE_PATH en var", function() {

            $_SERVER['BASE_PATH'] = '/api';
            $_SERVER['REQUEST_URI'] = '/api/controller/action';
            $_SERVER['SCRIPT_NAME'] = '/api/controller/action';

            $request = Request::ingoing();

            expect($request->basePath())->toBe('/api');
            expect($request->path())->toBe('/controller/action');

        });

        it("throw an exception when `'REQUEST_URI'` is missing", function() {

            $closure = function() {
                unset($_SERVER['REQUEST_URI']);
                $request = Request::ingoing();
            };

            expect($closure)->toThrow(new NetException("Missing `'REQUEST_URI'` environment variable, unable to create the main request."));

        });

        it("throw an exception when `'SCRIPT_NAME'` is missing", function() {

            $closure = function() {
                unset($_SERVER['SCRIPT_NAME']);
                $request = Request::ingoing();
            };

            expect($closure)->toThrow(new NetException("Missing `'SCRIPT_NAME'` environment variable, unable to create the main request."));

        });

    });

    describe("::files()", function() {

        it("normalizes deep `\$_FILES` structure", function() {

            $_FILES = [
                'files' => [
                    'name' => [
                        'file 2.jpg',
                        'file 3.jpg',
                        'file 4.jpg'
                    ],
                    'type' => [
                        'image/jpeg',
                        'image/jpeg',
                        'image/jpeg'
                    ],
                    'tmp_name' => [
                        '/private/var/tmp/phpF5vsky',
                        '/private/var/tmp/phphRJ2zW',
                        '/private/var/tmp/phprI92L1'
                    ],
                    'error' => [
                        0,
                        0,
                        0
                    ],
                    'size' => [
                         418,
                         418,
                         418
                    ]
                ]
            ];

            expect(Request::files())->toEqual([
                'files' => [
                    [
                        'name' => 'file 2.jpg',
                        'type' => 'image/jpeg',
                        'tmp_name' => '/private/var/tmp/phpF5vsky',
                        'error' => 0,
                        'size' => 418
                    ],
                    [
                        'name' => 'file 3.jpg',
                        'type' => 'image/jpeg',
                        'tmp_name' => '/private/var/tmp/phphRJ2zW',
                        'error' => 0,
                        'size' => 418
                    ],
                    [
                        'name' => 'file 4.jpg',
                        'type' => 'image/jpeg',
                        'tmp_name' => '/private/var/tmp/phprI92L1',
                        'error' => 0,
                        'size' => 418
                    ]
                ]
            ]);

        });

        it("normalizes nested `\$_FILES` structure", function() {

            $_FILES = [
                'Image' =>[
                    'name' => [
                        'file' => 'file 5.jpg'
                    ],
                    'type' => [
                        'file' => 'image/jpeg'
                    ],
                    'tmp_name' => [
                        'file' => '/private/var/tmp/phpAmSDL4'
                    ],
                    'error' => [
                        'file' => 0
                    ],
                    'size' => [
                        'file' => 418
                    ]
                ]
            ];

            expect(Request::files())->toEqual([
                'Image' => [
                    'file' => [
                        'name' => 'file 5.jpg',
                        'type' => 'image/jpeg',
                        'tmp_name' => '/private/var/tmp/phpAmSDL4',
                        'error' => 0,
                        'size' => 418
                    ]
                ]
            ]);

        });

        it("normalizes deeply nested `\$_FILES` structure", function() {

            $_FILES = [
                'Photo' => [
                    'name' => [
                        'files' => [
                            0 => 'file 6.jpg',
                            1 => 'file 7.jpg',
                            2 => 'file 8.jpg'
                        ]
                    ],
                    'type' => [
                        'files' => [
                            0 => 'image/jpeg',
                            1 => 'image/jpeg',
                            2 => 'image/jpeg'
                        ]
                    ],
                    'tmp_name' => [
                        'files' => [
                            0 => '/private/var/tmp/php2eViak',
                            1 => '/private/var/tmp/phpMsC5Pp',
                            2 => '/private/var/tmp/phpm2nm98'
                        ]
                    ],
                    'error' => [
                        'files' => [
                            0 => 0,
                            1 => 0,
                            2 => 0
                        ]
                    ],
                    'size' => [
                        'files' => [
                            0 => 418,
                            1 => 418,
                            2 => 418
                        ]
                    ]
                ]
            ];

            expect(Request::files())->toEqual([
                'Photo' => [
                    'files' => [
                        [
                            'name' => 'file 6.jpg',
                            'type' => 'image/jpeg',
                            'tmp_name' => '/private/var/tmp/php2eViak',
                            'error' => 0,
                            'size' => 418
                        ],
                        [
                            'name' => 'file 7.jpg',
                            'type' => 'image/jpeg',
                            'tmp_name' => '/private/var/tmp/phpMsC5Pp',
                            'error' => 0,
                            'size' => 418
                        ],
                        [
                            'name' => 'file 8.jpg',
                            'type' => 'image/jpeg',
                            'tmp_name' => '/private/var/tmp/phpm2nm98',
                            'error' => 0,
                            'size' => 418
                        ]
                    ]
                ]
            ]);

        });

    });

});
