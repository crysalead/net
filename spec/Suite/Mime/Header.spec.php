<?php
namespace Lead\Net\Spec\Suite\Mime;

use RuntimeException;
use Lead\Net\Mime\Header;

describe("Header", function() {

    describe("->toString()", function() {

        it("throws an exception if trying to generate a header line containing 998+ characters", function() {

            $closure = function() {
                $header = new Header('Subject', str_pad('to much', 998, "___"));
                $header->to('header');
            };

            expect($closure)->toThrow(new RuntimeException("A header line with more that 998 characters has been detected."));
        });

    });

});
