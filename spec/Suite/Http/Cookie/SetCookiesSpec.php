<?php
namespace Lead\Net\Spec\Suite\Http\Cookie;

use Exception;
use Lead\Net\Http\Cookie\SetCookie;
use Lead\Net\Http\Cookie\SetCookies;

describe("SetCookies", function() {

    beforeEach(function() {
        $this->setCookies = new SetCookies();
    });

    describe("->offsetSet()", function() {

        it("sets a Set-Cookie", function() {

            $this->setCookies['foo'] = 'bar';

            expect($this->setCookies['foo'][0]->value())->toBe('bar');

        });

        it("sets a Set-Cookie using the array syntax", function() {

            $this->setCookies['foo'] = [
                'value'    => 'bar',
                'expires'  => 'Thu, 25 Dec 2014 00:00:00 GMT',
                'httponly' => true
            ];
            expect($this->setCookies['foo'][0]->value())->toBe('bar');
            expect($this->setCookies['foo'][0]->expires())->toBe(1419465600);
            expect($this->setCookies['foo'][0]->httponly())->toBe(true);

        });

        it("sets a Set-Cookie using an instance", function() {

            $this->setCookies['foo'] = new SetCookie('bar');
            expect($this->setCookies['foo'][0]->value())->toBe('bar');

        });

        it("overrides a Set-Cookie", function() {

            $this->setCookies['foo'] = [
                'value'    => 'bar',
                'expires'  => 'Thu, 25 Dec 2014 00:00:00 GMT',
                'httponly' => true
            ];

            $this->setCookies['foo'] = [
                'value'    => 'baz',
                'expires'  => 'Fri, 25 Dec 2015 00:00:00 GMT',
                'httponly' => false
            ];
            expect($this->setCookies['foo'][0]->value())->toBe('baz');
            expect($this->setCookies['foo'][0]->expires())->toBe(1451001600);
            expect($this->setCookies['foo'][0]->httponly())->toBe(false);

        });

        it("throws an exception if the Set-Cookie is an invalid instance", function() {

            $closure = function() {
                $this->setCookies['foo'] = (object) 'bar';
            };
            expect($closure)->toThrow(new Exception('Error, only `Lead\Net\Http\Cookie\SetCookie` instances are allowed in this collection.'));

        });

        it("throws an exception for trying to get an unexisting Set-Cookie", function() {

            $closure = function() {
                $this->setCookies['foo'];
            };
            expect($closure)->toThrow(new Exception("Unexisting Set-Cookie `'foo'`."));

        });

        it("throws an exception when trying to set multiple values for a Set-Cookie", function() {

            $closure = function() {
                $this->setCookies['foo'] = 'bar';
                $this->setCookies['foo'][] = 'baz';
            };
            expect($closure)->toThrow('`E_NOTICE` Indirect modification of overloaded element of Lead\Net\Http\Cookie\SetCookies has no effect');

        });

        it("throws an exception with invalid names", function() {

            foreach (str_split("=,; \t\r\n\013\014") as $invalid) {

                $closure = function() use ($invalid) {
                    $this->setCookies["ab{$invalid}ba"] = 'bar';
                };
                expect($closure)->toThrow(new Exception("Invalid Set-Cookie name `'ab{$invalid}ba'`."));

            }

        });

    });

    describe("->offsetExists()", function() {

        it('checks if a Set-Cookie exists', function() {

            expect(isset($this->setCookies['foo']))->toBe(false);

            $this->setCookies['foo'] = 'bar';
            expect(isset($this->setCookies['foo']))->toBe(true);

        });

    });

    describe("->offsetUnset()", function() {

        it('unsets all Set-Cookie of the same name', function() {

            $this->setCookies['foo'] = 'bar';
            expect(isset($this->setCookies['foo']))->toBe(true);

            unset($this->setCookies['foo']);
            expect(isset($this->setCookies['foo']))->toBe(false);

        });

        it('bails out on unexisting cookie', function() {

            unset($this->setCookies['foo']);
            expect(isset($this->setCookies['foo']))->toBe(false);

        });

    });

    describe("->keys()", function() {

        it('returns existing cookie names', function() {

            $this->setCookies['foo'] = 'bar';
            $this->setCookies['baz'] = 'foo';

            expect($this->setCookies->keys())->toBe(['foo', 'baz']);

        });

    });

    describe("->flushExpired()", function() {

        it('generates HTTP headers', function() {

            $this->setCookies['foo1'] = ['value' => 'bar1', 'expires' => strtotime('+10 days')];
            $this->setCookies['foo2'] = ['value' => 'bar2', 'expires' => strtotime('-1 day')];
            $this->setCookies['foo3'] = ['value' => 'bar3', 'expires' => strtotime('+1 day')];

            $this->setCookies->flushExpired();

            expect($this->setCookies->keys())->toBe(['foo1', 'foo3']);

        });

    });

    describe("->to('header')", function() {

        it('generates HTTP headers', function() {

            $this->setCookies['foo1'] = 'bar1';
            $this->setCookies['foo2'] = 'bar2';
            $this->setCookies['foo3'] = 'bar3';

            expect($this->setCookies->to('header'))->toBe(join("\r\n", [
                'Set-Cookie: foo1=bar1; Path=/',
                'Set-Cookie: foo2=bar2; Path=/',
                'Set-Cookie: foo3=bar3; Path=/'
            ]));

        });

    });

    describe("->data()", function() {

        it('exports Set-Cookies', function() {

            $this->setCookies['foo1'] = ['value' => 'bar1', 'path' => '/home'];
            $this->setCookies['foo1'] = ['value' => 'bar11', 'path' => '/home/index'];
            $this->setCookies['foo2'] = ['value' => 'bar2', 'path' => '/'];

            expect($this->setCookies->data())->toBe([
                "foo1" => [
                    [
                        "value" => "bar1",
                        "expires" => null,
                        "path" => "/home",
                        "domain" => null,
                        "max-age" => null,
                        "secure" => false,
                        "httponly" => false
                    ],
                    [
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

    describe("::parseSetCookie()", function() {

        it("parses a Set-Cookie from an HTTP header", function() {

            $data = SetCookies::parse(
                'mycookie=the+cookie+value; Expires=Thu, 25 Dec 2014 00:00:00 GMT; Path=/blog; Domain=.domain.com; Secure; HttpOnly'
            );

            $this->expect($data)->toBe([[
                'secure'   => true,
                'domain'   => '.domain.com',
                'path'     => '/blog',
                'name'     => 'mycookie',
                'value'    => 'the cookie value',
                'expires'  => 'Thu, 25 Dec 2014 00:00:00 GMT',
                'httponly' => true
            ]]);

        });

    });

    describe("::toHeader()", function() {

        it('generates a Set-Cookie HTTP headers', function() {

            $cookies = new SetCookies();
            $cookies['foo1'] = 'bar1';
            $cookies['foo2'] = 'bar2';
            $cookies['foo3'] = 'bar3';

            expect(SetCookies::toHeader($cookies))->toBe(join("\r\n", [
                'Set-Cookie: foo1=bar1; Path=/',
                'Set-Cookie: foo2=bar2; Path=/',
                'Set-Cookie: foo3=bar3; Path=/'
            ]));

        });

        it("generates a custom Set-Cookie HTTP header", function() {

            $nextYear = date('Y') + 1;
            $expires = strtotime("{$nextYear}-12-25 00:00:00 UTC");

            $cookie = new SetCookie('the cookie value', [
                'expires'  => $expires,
                'path'     => '/blog',
                'domain'   => '.domain.com',
                'max-age'  => null,
                'secure'   => true,
                'httponly' => true
            ]);

            $date = gmdate('D, d M Y H:i:s \G\M\T', $expires);
            $this->expect(SetCookies::toHeader(['mycookie' => $cookie]))->toBe(
                "Set-Cookie: mycookie=the%20cookie%20value; Expires={$date}; Path=/blog; Domain=.domain.com; Secure; HttpOnly"
            );

        });

        it("generates a custom Set-Cookie HTTP header using Max-Age instead of Expires", function() {

            $cookie = new SetCookie('the cookie value', [
                'path'     => '/blog',
                'domain'   => '.domain.com',
                'max-age'  => '3600',
                'secure'   => true,
                'httponly' => true
            ]);

            $this->expect(SetCookies::toHeader(['mycookie' => $cookie]))->toBe(
                "Set-Cookie: mycookie=the%20cookie%20value; Max-Age=3600; Path=/blog; Domain=.domain.com; Secure; HttpOnly"
            );

        });

        it("ignores not setted values but Path", function() {

            $cookie = new SetCookie('the cookie value');

            $this->expect(SetCookies::toHeader(['mycookie' => $cookie]))->toBe(
                "Set-Cookie: mycookie=the%20cookie%20value; Path=/"
            );

        });

        it("throws an exception with invalid names", function() {

            foreach (str_split("=,; \t\r\n\013\014") as $invalid) {

                $closure = function() use ($invalid) {
                    $cookie = new SetCookie('foo');
                    SetCookies::toHeader(["ab{$invalid}ba" => $cookie]);
                };
                expect($closure)->toThrow(new Exception("Invalid Set-Cookie name `'ab{$invalid}ba'`."));

            }

        });

    });

});