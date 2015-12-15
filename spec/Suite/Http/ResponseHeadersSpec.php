<?php
namespace Lead\Net\Spec\Suite\Http;

use Exception;
use Lead\Net\NetException;
use Lead\Net\Http\Header;
use Lead\Net\Http\ResponseHeaders;

describe("ResponseHeaders", function() {

    beforeEach(function() {
        $this->headers = new ResponseHeaders();
    });

    describe("->add()", function() {

        it("adds set-cookies", function() {

            $this->headers->add('Set-Cookie: foo1=bar1; Path=/');
            $this->headers->add('Set-Cookie: foo2=bar2; Path=/');
            $this->headers->add('Set-Cookie: foo3=bar3; Path=/');


            $expected =<<<EOD
Set-Cookie: foo1=bar1; Path=/\r
Set-Cookie: foo2=bar2; Path=/\r
Set-Cookie: foo3=bar3; Path=/\r
\r

EOD;

            expect((string) $this->headers)->toBe($expected);

        });

    });

});