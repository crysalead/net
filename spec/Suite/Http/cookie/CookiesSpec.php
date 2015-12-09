<?php
namespace Lead\Net\Spec\Suite\Http\Cookie;

use Exception;
use Lead\Net\Http\Cookie\Cookie;
use Lead\Net\Http\Cookie\Cookies;

describe("Cookies", function() {

    beforeEach(function() {
        $this->cookies = new Cookies();
    });

    describe("->offsetSet()", function() {

        it('adds a cookie', function() {

            $this->cookies['foo'] = 'bar';
            expect($this->cookies['foo']->value())->toBe('bar');

        });

        it('adds a cookie using the array syntax', function() {

            $this->cookies['foo'] = ['value' => 'bar'];
            expect($this->cookies['foo']->value())->toBe('bar');

        });

        it('adds a cookie using an instance', function() {

            $this->cookies['foo'] = new Cookie('bar');
            expect($this->cookies['foo']->value())->toBe('bar');

        });

        it('throws an exception if the cookie is an invalid instance', function() {

            $closure = function() {
                $this->cookies['foo'] = (object) 'bar';
            };
            expect($closure)->toThrow(new Exception('Error, only `Lead\Net\Http\Cookie\Cookie` instances are allowed in this collection.'));

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

    describe("->to('header')", function() {

        it('generates HTTP Cookie header', function() {

            $this->cookies['foo1'] = 'bar1';
            $this->cookies['foo2'] = 'bar2';
            $this->cookies['foo3'] = 'bar3';

            expect($this->cookies->to('header'))->toBe("Cookie: foo1=bar1, foo2=bar2, foo3=bar3");

        });

    });

    describe("->data()", function() {

        it('exports cookies', function() {

            $this->cookies['foo1'] = 'bar1';
            $this->cookies['foo2'] = 'bar2';
            $this->cookies['foo3'] = 'bar3';

            expect($this->cookies->data())->toBe([
                'foo1' => 'bar1',
                'foo2' => 'bar2',
                'foo3' => 'bar3'
            ]);

        });

    });

    describe("::parseCookie()", function() {

        it("create a cookie from an HTTP header", function() {

            $data = Cookies::parseCookie('mycookie=the+cookie+value');

            $this->expect($data)->toBe([
                'name'     => 'mycookie',
                'value'    => 'the cookie value'
            ]);

        });

    });

    describe("::toCookie()", function() {

        it('generates HTTP Cookie header', function() {

            $cookies = new Cookies();
            $cookies['foo1'] = 'bar1';
            $cookies['foo2'] = 'bar2';
            $cookies['foo3'] = 'bar3';

            expect(Cookies::toCookie($cookies))->toBe("Cookie: foo1=bar1, foo2=bar2, foo3=bar3");

        });

        it("encodes values", function() {

            $cookie = new Cookie('the cookie value');
            $this->expect(Cookies::toCookie(['mycookie' => $cookie]))->toBe("Cookie: mycookie=the+cookie+value");

        });

        it("throws an exception with invalid names", function() {

            foreach (str_split("=,; \t\r\n\013\014") as $invalid) {

                $closure = function() use ($invalid) {
                    $cookie = new Cookie('foo');
                    Cookies::toCookie(["ab{$invalid}ba" => $cookie]);
                };
                expect($closure)->toThrow(new Exception("Invalid cookie name `'ab{$invalid}ba'`."));

            }

        });

    });

});