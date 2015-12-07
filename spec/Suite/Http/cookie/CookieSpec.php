<?php
namespace Lead\Net\Spec\Suite\Http\Cookie;

use Exception;
use Lead\Net\Http\Cookie\Cookie;

describe("Cookie", function() {

    describe("->value()", function() {

        it("gets the value", function() {

            $cookie = new Cookie('foo');
            $this->expect($cookie->value())->toBe('foo');

        });

        it("sets the value", function() {

            $cookie = new Cookie('foo');
            $cookie->value('bar');

            $this->expect($cookie->value())->toBe('bar');
        });

        it("casts value as string", function() {

            $cookie = new Cookie('foo');
            $cookie->value(0);

            $this->expect($cookie->value())->toBe('0');

        });

        it("throws an exception with empty value", function() {

            foreach (["", null] as $invalid) {
                $closure = function() use ($invalid) {
                    $cookie = new Cookie('foo');
                    $cookie->value($invalid);
                };
                expect($closure)->toThrow(new Exception("Invalid cookie value `'{$invalid}'`."));
            }

        });

    });

});
