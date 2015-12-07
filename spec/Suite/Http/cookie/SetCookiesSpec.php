<?php
namespace Lead\Net\Spec\Suite\Http\Cookie;

use Exception;
use Lead\Net\Http\Cookie\SetCookie;
use Lead\Net\Http\Cookie\SetCookies;

describe("SetCookies", function() {

    beforeEach(function() {
        $this->cookies = new SetCookies();
    });

    describe("->offsetSet()", function() {

        it("adds Cookies", function() {

            $this->cookies['foo'] = 'bar';

            expect($this->cookies['foo']->value())->toBe('bar');

        });

        it("adds a cookie using the array syntax", function() {

            $this->cookies['foo'] = [
                'value'    => 'bar',
                'expires'  => 'Thu, 25 Dec 2014 00:00:00 GMT',
                'httponly' => true
            ];
            expect($this->cookies['foo']->value())->toBe('bar');
            expect($this->cookies['foo']->expires())->toBe(1419465600);
            expect($this->cookies['foo']->httponly())->toBe(true);

        });

        it("adds a cookie using an instance", function() {

            $this->cookies['foo'] = new SetCookie('bar');
            expect($this->cookies['foo']->value())->toBe('bar');

        });

        it("throws an exception if the cookie is an invalid instance", function() {

            $closure = function() {
                $this->cookies['foo'] = (object) 'bar';
            };
            expect($closure)->toThrow(new Exception('Error, only `Lead\Net\Http\Cookie\SetCookie` instances are allowed in this collection.'));

        });

        it("throws an exception if the cookie scope doesn't match the collection scope", function() {

            $closure = function() {
                $this->cookies['foo'] = [
                    'value'  => 'bar',
                    'domain' => '.domain2.com'
                ];
            };
            expect($closure)->toThrow(new Exception("Error, the cookie's scope doesn't match the collection's one."));

        });

        it("throws an exception with invalid names", function() {

            foreach (str_split("=,; \t\r\n\013\014") as $invalid) {

                $closure = function() use ($invalid) {
                    $this->cookies["ab{$invalid}ba"] = 'bar';
                };
                expect($closure)->toThrow(new Exception("Invalid cookie name `'ab{$invalid}ba'`."));

            }

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

    describe("->to('header')", function() {

        it('generates HTTP headers', function() {

            $this->cookies['foo1'] = 'bar1';
            $this->cookies['foo2'] = 'bar2';
            $this->cookies['foo3'] = 'bar3';

            expect($this->cookies->to('header'))->toBe(join("\r\n", [
                'Set-Cookie: foo1=bar1; Path=/',
                'Set-Cookie: foo2=bar2; Path=/',
                'Set-Cookie: foo3=bar3; Path=/'
            ]));

        });

    });

    describe("::parseSetCookie()", function() {

        it("parses a cookie from an HTTP header", function() {

            $data = SetCookies::parseSetCookie(
                'mycookie=the+cookie+value; Expires=Thu, 25 Dec 2014 00:00:00 GMT; Path=/blog; Domain=.domain.com; Secure; HttpOnly'
            );

            $this->expect($data)->toBe([
                'secure'   => true,
                'domain'   => '.domain.com',
                'path'     => '/blog',
                'name'     => 'mycookie',
                'value'    => 'the cookie value',
                'expires'  => 'Thu, 25 Dec 2014 00:00:00 GMT',
                'httponly' => true
            ]);

        });

    });

    describe("::toSetCookie()", function() {

        it('generates a SetCookie HTTP headers', function() {

            $cookies = new SetCookies();
            $cookies['foo1'] = 'bar1';
            $cookies['foo2'] = 'bar2';
            $cookies['foo3'] = 'bar3';

            expect(SetCookies::toSetCookie($cookies))->toBe(join("\r\n", [
                'Set-Cookie: foo1=bar1; Path=/',
                'Set-Cookie: foo2=bar2; Path=/',
                'Set-Cookie: foo3=bar3; Path=/'
            ]));

        });

        it("generates a custom SetCookie HTTP header", function() {

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
            $this->expect(SetCookies::toSetCookie(['mycookie' => $cookie]))->toBe(
                "Set-Cookie: mycookie=the+cookie+value; Expires={$date}; Path=/blog; Domain=.domain.com; Secure; HttpOnly"
            );

        });

        it("generates a custom SetCookie HTTP header using Max-Age instead of Expires", function() {

            $cookie = new SetCookie('the cookie value', [
                'path'     => '/blog',
                'domain'   => '.domain.com',
                'max-age'  => '3600',
                'secure'   => true,
                'httponly' => true
            ]);

            $this->expect(SetCookies::toSetCookie(['mycookie' => $cookie]))->toBe(
                "Set-Cookie: mycookie=the+cookie+value; Max-Age=3600; Path=/blog; Domain=.domain.com; Secure; HttpOnly"
            );

        });

        it("ignores not setted values but Path", function() {

            $cookie = new SetCookie('the cookie value');

            $this->expect(SetCookies::toSetCookie(['mycookie' => $cookie]))->toBe(
                "Set-Cookie: mycookie=the+cookie+value; Path=/"
            );

        });

        it("throws an exception with invalid names", function() {

            foreach (str_split("=,; \t\r\n\013\014") as $invalid) {

                $closure = function() use ($invalid) {
                    $cookie = new SetCookie('foo');
                    SetCookies::toSetCookie(["ab{$invalid}ba" => $cookie]);
                };
                expect($closure)->toThrow(new Exception("Invalid cookie name `'ab{$invalid}ba'`."));

            }

        });

    });

});