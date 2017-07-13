<?php
namespace Lead\Net\Spec\Suite\Http;

use RuntimeException;
use Lead\Net\Http\Header;

describe("Header", function() {

    describe("->toString()", function() {

        it("throws an exception if trying to generate a header line containing 8000+ characters", function() {

            $closure = function() {
                $header = new Header('SetCookie', str_pad('to much', 8000, "___"));
                $header->to('header');
            };

            expect($closure)->toThrow(new RuntimeException("A header line with more that 8000 characters has been detected."));
        });

    });

});
