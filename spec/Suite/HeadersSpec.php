<?php
namespace Lead\Net\Spec\Suite;

use Exception;
use Lead\Net\Header;
use Lead\Net\Headers;

describe("Headers", function() {

    beforeEach(function() {
        $this->headers = new Headers();
    });

    describe("->offsetGet()/->offsetSet()", function() {

        it("sets an header", function() {

            $this->headers['Content-Type'] = 'text/plain';

            expect($this->headers['Content-Type']->data())->toBe('text/plain');
            expect($this->headers['Content-Type']->to('array'))->toBe(['text/plain']);

        });

        it("sets an header instance", function() {

            $this->headers['Content-Type'] = new Header([
                'data' => 'text/plain'
            ]);

            expect($this->headers['Content-Type']->data())->toBe('text/plain');
            expect($this->headers['Content-Type']->to('array'))->toBe(['text/plain']);

        });

        it("is case insensitive", function() {

            $this->headers['content-type'] = 'text/plain';
            $this->headers['CONTENT-TYPE'] = 'application/json';

            expect($this->headers['content-type']->data())->toBe('application/json');
            expect($this->headers['CONTENT-TYPE']->to('array'))->toBe(['application/json']);

        });

        it("sets multi values header", function() {

            $this->headers['Accept'] = 'text/html;q=1.0';
            $this->headers['Accept'][] = '*/*;q=0.1';

            expect($this->headers['Accept']->data())->toBe('text/html;q=1.0, */*;q=0.1');
            expect($this->headers['Accept']->to('array'))->toBe([
                'text/html;q=1.0',
                '*/*;q=0.1'
            ]);

        });

        it("overrides multi values header", function() {

            $this->headers['Accept'] = 'text/html;q=1.0';
            $this->headers['Accept'][] = '*/*;q=0.1';

            $this->headers['Accept'] = 'application/json';
            expect($this->headers['Accept']->data())->toBe('application/json');
            expect($this->headers['Accept']->to('array'))->toBe(['application/json']);

        });

        it("throws an exception with an empty name", function() {

            $closure = function() {
                $this->headers[] = 'value';
            };

            expect($closure)->toThrow(new Exception("Error, invalid header name, can't be empty."));

        });

        it("throws an exception with a non scalar value", function() {

            $closure = function() {
                $this->headers['name'] = ['value'];
            };

            expect($closure)->toThrow(new Exception("Error, only string value is allowed."));

        });

    });

    describe("->add()", function() {

        it("adds an header", function() {

            $this->headers->add('Content-Type: text/plain');

            expect($this->headers['Content-Type']->data())->toBe('text/plain');
            expect($this->headers['Content-Type']->to('array'))->toBe(['text/plain']);

        });

    });

    describe("->offsetExists()", function() {

        it("checks if a header exist", function() {

            $this->headers['Content-Type'] = 'text/plain';

            expect(isset($this->headers['Content-Type']))->toBe(true);
            expect(isset($this->headers['Accept']))->toBe(false);
        });

        it("is case insensitive", function() {

            $this->headers['CONTENT-TYPE'] = 'application/json';

            expect(isset($this->headers['content-type']))->toBe(true);
            expect(isset($this->headers['cOnTeNt-TyPe']))->toBe(true);

        });

    });

    describe("->offsetUnset()", function() {

        it("remove a header", function() {

            $this->headers['Content-Type'] = 'text/plain';
            unset($this->headers['Content-Type']);

            expect(isset($this->headers['Content-Type']))->toBe(false);
        });

        it("is case insensitive", function() {

            $this->headers['Content-Type'] = 'text/plain';
            unset($this->headers['CONTENT-TYPE']);

            expect(isset($this->headers['content-type']))->toBe(false);

        });

    });

    describe("->data()", function() {

        it("exports headers", function() {

            $this->headers['Content-Type'] = 'text/plain';
            $this->headers['X-Custom-ABC'] = 'abc';
            $this->headers['Accept'] = 'text/html;q=1.0';
            $this->headers['Accept'][] = '*/*;q=0.1';

            expect($this->headers->data())->toBe([
                'Content-Type: text/plain',
                'X-Custom-ABC: abc',
                'Accept: text/html;q=1.0, */*;q=0.1'
            ]);

        });

    });

    describe("->__toString()", function() {

        it("casts in string", function() {

            $header = <<<EOD
HTTP/1.1 200 OK
Date: Thu, 25 Dec 2014 00:00:00 GMT
Content-Type: text/html; charset=UTF-8
Vary: Accept-Encoding, Cookie, User-Agent


EOD;

            $headers = Headers::parse($header);
            expect((string) $headers)->toBe($header);

        });

    });

    describe("::parse()", function() {

        it("creates a collection of headers from an header string", function() {

            $header = <<<EOD
HTTP/1.1 200 OK
Date: Thu, 25 Dec 2014 00:00:00 GMT
Content-Type: text/html; charset=UTF-8
Vary: Accept-Encoding, Cookie, User-Agent

EOD;

            $headers = Headers::parse($header);
            expect($headers->data())->toBe([
                'HTTP/1.1 200 OK',
                'Date: Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type: text/html; charset=UTF-8',
                'Vary: Accept-Encoding, Cookie, User-Agent'
            ]);

        });

        it("creates a collection of headers from an array of headers", function() {

            $header = [
                'HTTP/1.1 200 OK',
                'Date: Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type: text/html; charset=UTF-8',
                'Vary: Accept-Encoding, Cookie, User-Agent'
            ];

            $headers = Headers::parse($header);
            expect($headers->data())->toBe([
                'HTTP/1.1 200 OK',
                'Date: Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type: text/html; charset=UTF-8',
                'Vary: Accept-Encoding, Cookie, User-Agent'
            ]);

        });

    });

});
