<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Net\Http\Headers;

describe("Headers", function() {

    beforeEach(function() {

        $this->headers = new Headers();

    });

    describe("->status()", function() {

        it("gets/sets the status", function() {

            expect($this->headers->status())->toBe(null);
            expect($this->headers->status('HTTP/1.1 200 OK'))->toBe($this->headers);
            expect($this->headers->status())->toBe('HTTP/1.1 200 OK');

            expect($this->headers->status('HTTP/1.0 404 Not Found'))->toBe($this->headers);
            expect($this->headers->status())->toBe('HTTP/1.0 404 Not Found');

            expect((string) $this->headers)->toBe("HTTP/1.0 404 Not Found\r\n\r\n");

        });

    });

    describe("->add()", function() {

        it("adds cookies", function() {

            $this->headers->add('Cookie: foo1=bar1, foo2=bar2, foo3=bar3');

            expect((string) $this->headers)->toBe("Cookie: foo1=bar1, foo2=bar2, foo3=bar3\r\n\r\n");

        });

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

        it("puts the status first", function() {

            $expected =<<<EOD
HTTP/1.1 200 OK\r
User-Agent: Mozilla/5.0\r
Cache-Control: no-cache\r
\r

EOD;

            $this->headers->add('User-Agent: Mozilla/5.0');
            $this->headers->add('Cache-Control: no-cache');
            $this->headers->add('HTTP/1.1 200 OK');

            expect((string) $this->headers)->toBe($expected);

        });

    });

});