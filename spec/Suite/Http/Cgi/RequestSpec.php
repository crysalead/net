<?php
namespace Lead\Net\Spec\Suite\Http\Cgi;

use Lead\Set\Set;
use Lead\Net\NetException;
use Lead\Net\Http\Cgi\Request;

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

describe("Request", function() {

    before(function() {
        $this->globalNames =['_GET', '_POST', '_SERVER'];
        $this->oldEnv = [];
        foreach ($this->globalNames as $varname) {
            $this->oldEnv[$varname] = $GLOBALS[$varname];
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

            expect($request->data())->toEqual([
                'basePath' => '',
                'locale'   => null,
                'data'     => [],
                'params'   => [],
                'env'      => $request->env,
                'method'   => 'GET',
                'scheme'   => 'http',
                'version'  => '1.1',
                'host'     => 'localhost',
                'port'     => 80,
                'path'     => '/',
                'query'    => '',
                'fragment' => '',
                'username' => null,
                'password' => null,
                'url'      => 'http://localhost/',
                'stream'   => $request->stream()
            ]);

            $expected = <<<EOD
Host: localhost\r
Connection: Close\r
User-Agent: Mozilla/5.0\r
Content-Type: text/html; charset=utf-8\r
\r

EOD;

            expect((string) $request->headers)->toBe($expected);

        });

        it("uses `'CONTENT_LENGTH'` from environment variables if exists and not set", function() {

            $request = new Request([
                'env' => [
                    'CONTENT_LENGTH' => 50
                ]
            ]);
            expect($request->headers['Content-Length']->value())->toEqual(50);

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
Content-Type: text/html; charset=utf-8\r
Accept: *.*\r
Accept-Charset: iso-8859-1, *, utf-8\r
Accept-Encoding: gzip\r
Connection: Keep-Alive\r
Referer: www.search-engine.com\r
User-Agent: Mozilla/3.0\r
\r

EOD;

            expect((string) $request->headers)->toBe($expected);
            expect((string) $request->host())->toBe('www.example.com');

        });

        it("allows `data['_method']` to override `'REQUEST_METHOD'`", function() {

            $request = new Request(['data' => ['_method' => 'PATCH']]);

            expect($request->method())->toBe('PATCH');
            expect($request->env['REQUEST_METHOD'])->toBe('PATCH');

        });

        it("extracts username & password from Basic authorization", function() {

            $request = new Request(['env' => [
                'HTTP_AUTHORIZATION' => 'Basic d2lsbHk6YWJjZGVm'
            ]]);

            expect($request->username())->toBe('willy');
            expect($request->password())->toBe('abcdef');
            expect($request->headers['Authorization']->value())->toBe('Basic d2lsbHk6YWJjZGVm');

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
            expect($request->headers['Authorization']->value())->toBe($digest);

        });

        it("applies the ignore pattern to the base path", function() {

            $request = new Request([
                'basePath' => '/base/path/webroot/index.php',
                'ignore'   => '/webroot/index.php'
            ]);

            expect($request->basePath())->toBe('/base/path');

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
                'ignore' => '/webroot/index.php'
            ]);

            expect($request->basePath())->toBe('');

            $request->basePath('/base/path/webroot/index.php');
            expect($request->basePath())->toBe('/base/path');

        });

    });

    describe("->ignore()", function() {

        it("sets the ignore pattern", function() {

            $request = new Request();
            expect($request->ignore())->toBe(null);

            $request->ignore('/webroot/index.php');
            expect($request->ignore())->toBe('/webroot/index.php');

        });

        it("lazily applies the ignore pattern on the base path", function() {

            $request = new Request([
                'basePath' => '/base/path/webroot/index.php'
            ]);

            expect($request->basePath())->toBe('/base/path/webroot/index.php');

            $request->ignore('/webroot/index.php');
            expect($request->basePath())->toBe('/base/path');

            $request->ignore(null);
            expect($request->basePath())->toBe('/base/path/webroot/index.php');

        });

    });

    describe("->accepts()", function() {

        it("parses accept header", function() {

            $request = new Request();
            $request->headers['Accept'] = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1,text/html;level=2;q=0.4, */*;q=0.5';

            expect($request->accepts())->toEqual([
                "text/html;level=1" => 1,
                "text/html" => 0.7,
                "text/html;level=2" => 0.4,
                "text/*" => 0.3,
                "*/*" => 0.5
            ]);
        });

    });

    describe("->locale()", function() {

        it("gets the locale", function() {

            $request = new Request(['locale' => 'en']);
            expect($request->locale())->toBe('en');

        });

        it("gets the locale from params", function() {

            $request = new Request(['params' => ['locale' => 'en']]);
            expect($request->locale())->toBe('en');

        });

        it("returns the locale if exists first", function() {

            $request = new Request([
                'locale' => 'es',
                'params' => ['locale' => 'en']
            ]);
            expect($request->locale())->toBe('es');

        });

        it("sets the locale if exists first", function() {

            $request = new Request(['locale' => 'en']);
            $request->locale('fr');
            expect($request->locale())->toBe('fr');

        });

    });

    describe("->referer()", function() {

        it("parses accept header", function() {

            $request = new Request();
            $request->headers['Accept'] = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1,text/html;level=2;q=0.4, */*;q=0.5';

            expect($request->accepts())->toEqual([
                "text/html;level=1" => 1,
                "text/html" => 0.7,
                "text/html;level=2" => 0.4,
                "text/*" => 0.3,
                "*/*" => 0.5
            ]);
        });

    });

    describe("::ingoing()", function() {

        beforeEach(function() {

            $this->globals = defineGlobals();

        });

        it("creates a request from globals", function() {

            $request = Request::ingoing();

            expect($request->data())->toEqual([
                'basePath' => '/base/path/webroot/index.php',
                'locale'   => null,
                'data'     => $_FILES + $_POST,
                'params'   => [],
                'env'      => $request->env,
                'method'   => 'GET',
                'scheme'   => 'http',
                'version'  => '1.1',
                'host'     => 'localhost',
                'port'     => 80,
                'path'     => '/app',
                'query'    => '?get=value',
                'fragment' => '',
                'username' => null,
                'password' => null,
                'url'      => 'http://localhost/app?get=value',
                'stream'   => $request->stream()
            ]);

        });

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

            $request = Request::ingoing();

            expect($request->data)->toEqual([
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
            ] + $_POST);

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

            $request = Request::ingoing();

            expect($request->data)->toEqual([
                'Image' => [
                    'file' => [
                        'name' => 'file 5.jpg',
                        'type' => 'image/jpeg',
                        'tmp_name' => '/private/var/tmp/phpAmSDL4',
                        'error' => 0,
                        'size' => 418
                    ]
                ]
            ] + $_POST);

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

            $request = Request::ingoing();

            expect($request->data)->toEqual([
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
            ] + $_POST);

        });

        it("supports rewrite", function() {

            $_SERVER['REQUEST_URI'] = '/base/path/webroot/app?get=value';
            $_SERVER['SCRIPT_NAME'] = '/base/path/webroot/index.php';

            $request = Request::ingoing();

            expect($request->basePath())->toBe('/base/path/webroot');

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

});
