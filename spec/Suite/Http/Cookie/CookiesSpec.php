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

        it('sets a cookie', function() {

            $this->cookies['foo'] = 'bar';
            expect($this->cookies['foo']->value())->toBe('bar');

        });

        it('sets a cookie using the array syntax', function() {

            $this->cookies['foo'] = ['value' => 'bar'];
            expect($this->cookies['foo']->value())->toBe('bar');

        });

        it('sets a cookie using an instance', function() {

            $this->cookies['foo'] = new Cookie('bar');
            expect($this->cookies['foo']->value())->toBe('bar');

        });

        it("sets multiple values for a cookie", function() {

            $this->cookies['foo1'] = new Cookie('bar1');
            $this->cookies['foo1'][] = new Cookie('bar2');
            $this->cookies['foo1'][] = new Cookie('bar3');

            expect($this->cookies->to('header'))->toBe("Cookie: foo1=bar1; foo1=bar2; foo1=bar3");

        });

        it("overrides a cookie value", function() {

            $this->cookies['foo1'] = new Cookie('bar1');
            $this->cookies['foo1'] = new Cookie('bar2');

            expect($this->cookies->to('header'))->toBe("Cookie: foo1=bar2");

        });

        it('throws an exception if the cookie is an invalid instance', function() {

            $closure = function() {
                $this->cookies['foo'] = (object) 'bar';
            };
            expect($closure)->toThrow(new Exception('Error, only `Lead\Net\Http\Cookie\Cookie` instances are allowed in this collection.'));

        });

        it("throws an exception for trying to get an unexisting cookie", function() {

            $closure = function() {
                $this->cookies['foo'];
            };
            expect($closure)->toThrow(new Exception("Unexisting Cookie `'foo'`."));

        });

        it("throws an exception with invalid names", function() {

            foreach (str_split("=,; \t\r\n\013\014") as $invalid) {

                $closure = function() use ($invalid) {
                    $this->cookies["ab{$invalid}ba"] = 'bar';
                };
                expect($closure)->toThrow(new Exception("Invalid Cookie name `'ab{$invalid}ba'`."));

            }

        });

    });

    describe("->offsetExists()", function() {

        it('checks if a cookie exists', function() {

            expect(isset($this->cookies['foo']))->toBe(false);

            $this->cookies['foo'] = 'bar';
            expect(isset($this->cookies['foo']))->toBe(true);

        });

    });

    describe("->offsetUnset()", function() {

        it('unsets all cookies', function() {

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

    describe("->to('header')", function() {

        it('generates HTTP Cookie header', function() {

            $this->cookies['foo1'] = 'bar1';
            $this->cookies['foo2'] = 'bar2';
            $this->cookies['foo3'] = 'bar3';

            expect($this->cookies->to('header'))->toBe("Cookie: foo1=bar1; foo2=bar2; foo3=bar3");

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

            $cookies = Cookies::parse('mycookie=the+cookie+value; mycookie2=the+cookie2+value');

            $this->expect($cookies)->toEqual([
                [
                    'name'     => 'mycookie',
                    'value'    => [
                        'the cookie value'
                    ]
                ],
                [
                    'name'     => 'mycookie2',
                    'value'    => [
                        'the cookie2 value'
                    ]
                ]
            ]);

        });

    });

    describe("::toHeader()", function() {

        it('generates HTTP Cookie header', function() {

            $cookies = new Cookies();
            $cookies['foo1'] = 'bar1';
            $cookies['foo2'] = 'bar2';
            $cookies['foo3'] = 'bar3';

            expect(Cookies::toHeader($cookies))->toBe("Cookie: foo1=bar1; foo2=bar2; foo3=bar3");

        });

        it("encodes values", function() {

            $cookie = new Cookie('the cookie value');
            $this->expect(Cookies::toHeader(['mycookie' => $cookie]))->toBe("Cookie: mycookie=the+cookie+value");

        });

        it("throws an exception with invalid names", function() {

            foreach (str_split("=,; \t\r\n\013\014") as $invalid) {

                $closure = function() use ($invalid) {
                    $cookie = new Cookie('foo');
                    Cookies::toHeader(["ab{$invalid}ba" => $cookie]);
                };
                expect($closure)->toThrow(new Exception("Invalid cookie name `'ab{$invalid}ba'`."));

            }

        });

    });

});