<?php
namespace Lead\Net\Spec\Suite\Http\Cookie;

use Exception;
use Lead\Net\Http\Cookie\Cookie;

describe("Cookie", function() {

    describe("->toString()", function() {

        it("returns a string representation of a cookie", function() {

            $cookie = new Cookie('bar1');
            $cookie[] = 'bar2';
            expect($cookie->toString('test'))->toBe('test=bar1; test=bar2');

        });

    });

});