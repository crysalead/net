<?php
namespace Lead\Net\Spec\Suite\Http\Psr7;

use InvalidArgumentException;
use Lead\Net\Http\Psr7\Uri;

describe("Uri", function() {

    beforeEach(function() {

        $this->invalidValues = [
            'null'       => null,
            'true'       => true,
            'false'      => false,
            'zero'       => 0,
            'int'        => 1,
            'zero-float' => 0.0,
            'float'      => 1.1,
            'array'      => ['value'],
            'object'     => (object)['value' => 'value'],
        ];

        $this->expectInvalid = function($method, $invalidValues) {

            foreach ($invalidValues as $value) {

                $closure = function() use ($method, $value) {
                    $uri = new Uri('https://example.com/');
                    $uri->$method($value);
                };

                expect($closure)->toThrow(new InvalidArgumentException());
            }
        };
    });

    describe("->__construct()", function() {

        it("sets default values", function() {

            $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
            expect($uri->getScheme())->toBe('https');
            expect($uri->getUserInfo())->toBe('user:pass');
            expect($uri->getHost())->toBe('local.example.com');
            expect($uri->getPort())->toBe(3001);
            expect($uri->getAuthority())->toBe('user:pass@local.example.com:3001');
            expect($uri->getPath())->toBe('/foo');
            expect($uri->getQuery())->toBe('bar=baz');
            expect($uri->getFragment())->toBe('quz');

        });

        it("leaves empty path of origin form when empty", function() {

            $uri = new Uri('?foo=bar');
            expect($uri->getPath())->toBe('');

        });

        it("throws an exception for invalid url", function() {

            $urls = [
                'null'    =>  null,
                'true'    =>  true,
                'false'   =>  false,
                'int'     =>  1,
                'float'   =>  1.1,
                'array'   =>  [ 'http://example.com/' ],
                'object'  =>  (object) [ 'uri' => 'http://example.com/' ],
                'invalid' =>  'http:///www.php-fig.org/'
            ];

            foreach ($urls as $url) {

                $closure = function() use ($url) {
                    new Uri($url);
                };

                expect($closure)->toThrow(new InvalidArgumentException());

            };

        });

        it("throws an exception with unsupported scheme", function() {

            $schemes =  [
                'mailto' => 'mailto',
                'ftp'    => 'ftp',
                'telnet' => 'telnet',
                'ssh'    => 'ssh',
                'git'    => 'git',
            ];

            foreach ($schemes as $scheme) {

                $closure = function() use ($scheme) {
                    $uri = new Uri($scheme . '://example.com');
                };
                expect($closure)->toThrow(new InvalidArgumentException('~Unsupported scheme~'));
            }

        });

    });

    describe("->__toString()", function() {

        it("exports the URI as a string", function() {
            $url = 'https://user:pass@local.example.com:3001/foo?bar=baz#quz';
            $uri = new Uri($url);
            expect((string) $uri)->toBe($url);
        });

        it("emits origin from the URL", function() {

            $url = '/foo/bar?baz=bat';
            $uri = new Uri($url);
            expect((string) $uri)->toBe($url);

        });

        it("only exports domain on empty path", function() {

            $url = 'http://example.com';
            $uri = new Uri($url);
            expect((string) $uri)->toBe($url);

        });

        it("exports empty path of origin form when empty", function() {

            $uri = new Uri('?foo=bar');
            expect($uri->getPath())->toBe('');

        });

        it("doesn't prefix with slash", function() {

            $uri = new Uri('http://example.com');
            $new = $uri->withPath('foo/bar');
            expect((string) $new)->toBe('http://example.com/foo/bar');

        });

        it("trims slashes to prevent XSS", function() {

            $url = 'http://example.org//zend.com';
            $uri = new Uri($url);
            expect((string) $uri)->toBe('http://example.org/zend.com');

        });

    });

    describe("->withScheme()", function() {

        it("returns a new instance with the new scheme", function() {

            $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
            $new = $uri->withScheme('http');
            expect($new)->not->toBe($uri);
            expect($new->getScheme())->toBe('http');
            expect((string) $new)->toBe('http://user:pass@local.example.com:3001/foo?bar=baz#quz');

        });

        it("strips off delimiter on mutating scheme", function() {

            $uri = new Uri('http://example.com');
            $new = $uri->withScheme('https://');
            expect($new->getScheme())->toBe('https');

        });

        it("throws an exception with unsupported scheme", function() {

            $schemes =  [
                'mailto' => 'mailto',
                'ftp'    => 'ftp',
                'telnet' => 'telnet',
                'ssh'    => 'ssh',
                'git'    => 'git',
            ];

            foreach ($schemes as $scheme) {

                $closure = function() use ($scheme) {
                    $uri = new Uri('http://example.com');
                    $uri->withScheme($scheme);
                };
                expect($closure)->toThrow(new InvalidArgumentException('~Unsupported scheme~'));
            }

        });

        it("throws an exception for invalid values", function() {

            $this->expectInvalid('withScheme', $this->invalidValues);

        });

    });

    describe("->withUserInfo()", function() {

        it("returns a new instance with the provided user", function() {

            $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
            $new = $uri->withUserInfo('matthew');
            expect($new)->not->toBe($uri);
            expect($new->getUserInfo())->toBe('matthew');
            expect((string) $new)->toBe('https://matthew@local.example.com:3001/foo?bar=baz#quz');

        });

        it("returns a new instance with the provided user and password", function() {

            $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
            $new = $uri->withUserInfo('matthew', 'zf2');
            expect($new)->not->toBe($uri);
            expect($new->getUserInfo())->toBe('matthew:zf2');
            expect((string) $new)->toBe('https://matthew:zf2@local.example.com:3001/foo?bar=baz#quz');

        });

        it("throws an exception for invalid values", function() {

            $this->expectInvalid('withUserInfo', $this->invalidValues);

        });

    });

    describe("->withHost()", function() {

        it("returns a new instance with the provided host", function() {

            $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
            $new = $uri->withHost('framework.zend.com');
            expect($new)->not->toBe($uri);
            expect($new->getHost())->toBe('framework.zend.com');
            expect((string) $new)->toBe('https://user:pass@framework.zend.com:3001/foo?bar=baz#quz');

        });

        it("throws an exception for invalid values", function() {

            $this->expectInvalid('withHost', $this->invalidValues);

        });

    });

    describe("->withPort()", function() {

        it("returns a new instance with the provided port", function() {

            foreach ([null, 3000, '3000'] as $port) {

                $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
                $new = $uri->withPort($port);
                expect($new)->not->toBe($uri);
                expect($new->getPort())->toEqual($port);
                expect((string) $new)->toBe(sprintf('https://user:pass@local.example.com%s/foo?bar=baz#quz', $port === null ? '' : ':' . $port));

            };

        });

        it("throws an exception for invalid port", function() {

            foreach ([true, false, 'string', [ 3000 ], (object) [ 3000 ], 0, -1, 65536] as $port) {

                $closure = function() use ($port) {
                    $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
                    $new = $uri->withPort($port);
                };

                expect($closure)->toThrow(new InvalidArgumentException('~Invalid port~'));

            };

        });

    });

    describe("->withPath()", function() {

        it("returns a new instance with the provided path", function() {

            $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
            $new = $uri->withPath('/bar/baz');
            expect($new)->not->toBe($uri);
            expect($new->getPath())->toBe('/bar/baz');
            expect((string) $new)->toBe('https://user:pass@local.example.com:3001/bar/baz?bar=baz#quz');

        });

        it("returns an empty path when setted as empty on an absolute URI", function() {

            $uri = new Uri('http://example.com/foo');
            $new = $uri->withPath('');
            expect($new->getPath())->toBe('');

        });

        it("doesn't prefix with slash", function() {

            $uri = new Uri('http://example.com');
            $new = $uri->withPath('foo/bar');
            expect($new->getPath())->toBe('foo/bar');

        });

        it("encodes path", function() {

            $uri = (new Uri())->withPath('/foo^bar');
            $expected = '/foo%5Ebar';
            expect($uri->getPath())->toBe($expected);

        });

        it("doesn't double encode path", function() {

            $uri = (new Uri())->withPath('/foo%5Ebar');
            $expected = '/foo%5Ebar';
            expect($uri->getPath())->toBe($expected);

        });

        it("throws an exception for invalid values", function() {

            $this->expectInvalid('withPath', $this->invalidValues + [
                'array'     => [ '/bar/baz' ],
                'object'    => (object) [ '/bar/baz' ],
                'query'     => '/bar/baz?bat=quz',
                'fragment'  => '/bar/baz#bat'
            ]);

        });

    });

    describe("->withQuery()", function() {

        it("returns a new instance with the provided query", function() {

            $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
            $new = $uri->withQuery('baz=bat');
            expect($new)->not->toBe($uri);
            expect($new->getQuery())->toBe('baz=bat');
            expect((string) $new)->toBe('https://user:pass@local.example.com:3001/foo?baz=bat#quz');

        });

        it("strips query prefix if present", function() {

            $uri = new Uri('http://example.com');
            $new = $uri->withQuery('?foo=bar');
            expect($new->getQuery())->toBe('foo=bar');

        });

        context("using data", function() {

            beforeEach(function() {

                $this->data = [
                    'key-only' => ['k^ey', 'k%5Eey'],
                    'key-value' => ['k^ey=valu`', 'k%5Eey=valu%60'],
                    'array-key-only' => ['key[]', 'key%5B%5D'],
                    'array-key-value' => ['key[]=valu`', 'key%5B%5D=valu%60'],
                    'complex' => ['k^ey&key[]=valu`&f<>=`bar', 'k%5Eey&key%5B%5D=valu%60&f%3C%3E=%60bar'],
                ];

            });

            it ("encodes query strings correctly", function() {

                foreach ($this->data as $data) {
                    list($query, $expected) = $data;
                    $uri = (new Uri())->withQuery($query);
                    expect($uri->getQuery())->toBe($expected);
                }

            });

            it ("doesn't double encodes query strings", function() {

                foreach ($this->data as $data) {
                    list($query, $expected) = $data;
                    $uri = (new Uri())->withQuery($expected);
                    expect($uri->getQuery())->toBe($expected);
                }

            });

        });

        it("throws an exception for invalid values", function() {

            $this->expectInvalid('withQuery', $this->invalidValues + [
                'array'     => [ 'baz=bat' ],
                'object'    => (object) [ 'baz=bat' ],
                'fragment'  => 'baz=bat#quz'
            ]);

        });

    });

    describe("->withFragment()", function() {

        it("returns a new instance with the provided fragment", function() {

            $uri = new Uri('https://user:pass@local.example.com:3001/foo?bar=baz#quz');
            $new = $uri->withFragment('qat');
            expect($new)->not->toBe($uri);
            expect($new->getFragment())->toBe('qat');
            expect((string) $new)->toBe('https://user:pass@local.example.com:3001/foo?bar=baz#qat');

        });

        it("strips fragment prefix if present", function() {

            $uri = new Uri('http://example.com');
            $new = $uri->withFragment('#/foo/bar');
            expect($new->getFragment())->toBe('/foo/bar');

        });

        it ("encodes fragments correctly", function() {

            $uri = (new Uri())->withFragment('/p^th?key^=`bar#b@z');
            $expected = '/p%5Eth?key%5E=%60bar%23b@z';
            expect($uri->getFragment())->toBe($expected);

        });

        it ("doesn't double encodes fragments", function() {

            $expected = '/p%5Eth?key%5E=%60bar%23b@z';
            $uri = (new Uri())->withFragment($expected);
            expect($uri->getFragment())->toBe($expected);

        });

        it("throws an exception for invalid values", function() {

            $this->expectInvalid('withFragment', $this->invalidValues);

        });

    });

    describe("->authorityInfo()", function() {

        it("retrieves authority", function() {

            $authorities = [
                'host-only'      => [ 'http://foo.com/bar',         'foo.com' ],
                'host-port'      => [ 'http://foo.com:3000/bar',    'foo.com:3000' ],
                'user-host'      => [ 'http://me@foo.com/bar',      'me@foo.com' ],
                'user-host-port' => [ 'http://me@foo.com:3000/bar', 'me@foo.com:3000' ],
            ];

            foreach ($authorities as $data) {
                list($url, $expected) = $data;
                $uri = new Uri($url);
                expect($uri->getAuthority())->toBe($expected);
            }

        });

    });

    describe("->getAuthority()", function() {

        it("omits port for standard scheme port combinations", function() {

            $ports = [
                'http'  => 80,
                'https' => 443
            ];

            foreach ($ports as $scheme => $port) {
                $uri = (new Uri())
                    ->withHost('example.com')
                    ->withScheme($scheme)
                    ->withPort($port);
                expect($uri->getAuthority())->toBe('example.com');
            }

        });

    });

});
