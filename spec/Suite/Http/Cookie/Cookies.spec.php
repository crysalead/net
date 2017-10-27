<?php
namespace Lead\Net\Spec\Suite\Http\Cookie;

use RuntimeException;
use Lead\Net\Http\Request;
use Lead\Net\Http\Cookie\Cookie;
use Lead\Net\Http\Cookie\Cookies;

describe("Cookies", function() {

    beforeEach(function() {
        $this->cookies = new Cookies();
    });

    describe("->offsetSet()", function() {

        it("sets a Set-Cookie", function() {

            $this->cookies['foo'] = 'bar';

            expect($this->cookies['foo'][0]->value())->toBe('bar');

        });

        it("sets a Set-Cookie using the array syntax", function() {

            $this->cookies['foo'] = [
                'value'    => 'bar',
                'expires'  => 'Thu, 25 Dec 2014 00:00:00 GMT',
                'httponly' => true
            ];
            expect($this->cookies['foo'][0]->value())->toBe('bar');
            expect($this->cookies['foo'][0]->expires())->toBe(1419465600);
            expect($this->cookies['foo'][0]->httponly())->toBe(true);

        });

        it("sets a Set-Cookie using an instance", function() {

            $this->cookies['foo'] = new Cookie(['value' => 'bar']);
            expect($this->cookies['foo'][0]->value())->toBe('bar');

        });

        it("overrides a Set-Cookie", function() {

            $this->cookies['foo'] = [
                'value'    => 'bar',
                'expires'  => 'Thu, 25 Dec 2014 00:00:00 GMT',
                'httponly' => true
            ];

            $this->cookies['foo'] = [
                'value'    => 'baz',
                'expires'  => 'Fri, 25 Dec 2015 00:00:00 GMT',
                'httponly' => false
            ];
            expect($this->cookies['foo'][0]->value())->toBe('baz');
            expect($this->cookies['foo'][0]->expires())->toBe(1451001600);
            expect($this->cookies['foo'][0]->httponly())->toBe(false);

        });

        it("throws an exception if the Set-Cookie is an invalid instance", function() {

            $closure = function() {
                $this->cookies['foo'] = (object) 'bar';
            };
            expect($closure)->toThrow(new RuntimeException('Error, only `Lead\Net\Http\Cookie\Cookie` instances are allowed in this collection.'));

        });

        it("throws an exception for trying to get an unexisting Set-Cookie", function() {

            $closure = function() {
                $this->cookies['foo'];
            };
            expect($closure)->toThrow(new RuntimeException("Unexisting Set-Cookie `'foo'`."));

        });

        it("throws an exception when trying to set multiple values for a Set-Cookie", function() {

            skipIf(defined('HHVM_VERSION'));

            $closure = function() {
                $this->cookies['foo'] = 'bar';
                $this->cookies['foo'][] = 'baz';
            };
            expect($closure)->toThrow('`E_NOTICE` Indirect modification of overloaded element of Lead\Net\Http\Cookie\Cookies has no effect');

        });

    });

    describe("->offsetExists()", function() {

        it('checks if a Set-Cookie exists', function() {

            expect(isset($this->cookies['foo']))->toBe(false);

            $this->cookies['foo'] = 'bar';
            expect(isset($this->cookies['foo']))->toBe(true);

        });

    });

    describe("->offsetUnset()", function() {

        it('unsets all Set-Cookie of the same name', function() {

            $this->cookies['foo'] = 'bar';
            expect(isset($this->cookies['foo']))->toBe(true);

            unset($this->cookies['foo']);
            expect(isset($this->cookies['foo']))->toBe(false);

        });

        it('bails out on unexisting cookie', function() {

            unset($this->cookies['foo']);
            expect(isset($this->cookies['foo']))->toBe(false);

        });

    });

    describe("->keys()", function() {

        it('returns existing cookie names', function() {

            $this->cookies['foo'] = 'bar';
            $this->cookies['baz'] = 'foo';

            expect($this->cookies->keys())->toBe(['foo', 'baz']);

        });

    });

    describe("->flushExpired()", function() {

        it('generates HTTP headers', function() {

            $this->cookies['foo1'] = ['value' => 'bar1', 'expires' => strtotime('+10 days')];
            $this->cookies['foo2'] = ['value' => 'bar2', 'expires' => strtotime('-1 day')];
            $this->cookies['foo3'] = ['value' => 'bar3', 'expires' => strtotime('+1 day')];

            $this->cookies->flushExpired();

            expect($this->cookies->keys())->toBe(['foo1', 'foo3']);

        });

    });

    describe("->data()", function() {

        it('exports Set-Cookies', function() {

            $this->cookies['foo1'] = ['value' => 'bar1', 'path' => '/home'];
            $this->cookies['foo1'] = ['value' => 'bar11', 'path' => '/home/index'];
            $this->cookies['foo2'] = ['value' => 'bar2', 'path' => '/'];

            expect($this->cookies->data())->toBe([
                "foo1" => [
                    [
                        "name" => "foo1",
                        "value" => "bar1",
                        "expires" => null,
                        "path" => "/home",
                        "domain" => null,
                        "max-age" => null,
                        "secure" => false,
                        "httponly" => false
                    ],
                    [
                        "name" => "foo1",
                        "value" => "bar11",
                        "expires" => null,
                        "path" => "/home/index",
                        "domain" => null,
                        "max-age" => null,
                        "secure" => false,
                        "httponly" => false
                    ]
                ],
                "foo2" => [
                    [
                        "name" => "foo2",
                        "value" => "bar2",
                        "expires" => null,
                        "path" => "/",
                        "domain" => null,
                        "max-age" => null,
                        "secure" => false,
                        "httponly" => false
                    ]
                ]
            ]);

        });

    });

    describe("::fromString()", function() {

        it("parses a Set-Cookie from an HTTP header", function() {

            $setCookie = Cookie::fromString(
                'mycookie=the+cookie+value; Expires=Thu, 25 Dec 2014 00:00:00 GMT; Path=/blog; Domain=.domain.com; Secure; HttpOnly'
            );

            $this->expect($setCookie->data())->toBe([
                'name'     => 'mycookie',
                'value'    => 'the cookie value',
                'expires'  => 1419465600,
                'path'     => '/blog',
                'domain'   => '.domain.com',
                'max-age'  => null,
                'secure'   => true,
                'httponly' => true
            ]);

        });

    });

});