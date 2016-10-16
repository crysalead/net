<?php
namespace Lead\Net\Spec\Suite;

use Exception;
use Lead\Net\Http\Header;

describe("Header", function() {

    beforeEach(function() {
        $this->header = new Header();
    });

    describe("->name()", function() {

        it("gets/sets the name", function() {

            $this->header->name('Content-Type');
            expect($this->header->name())->toBe('Content-Type');

        });

    });

    describe("->offsetSet()", function() {

        it("sets a value", function() {

            $this->header[] = 'valueX';
            $this->header[] = 'value2';
            $this->header[0] = 'value1';

            expect($this->header->value())->toBe('value1, value2');

        });

        it("throws an exception with string as name", function() {

            $closure = function() {
                $this->header['name'] = 'value';
            };

            expect($closure)->toThrow(new Exception('Invalid index, should be numeric or empty.'));

        });

    });

    describe("->value()", function() {

        it("returns a string representation of the value(s)", function() {

            $this->header[] = 'value1';
            $this->header[] = 'value2';
            $this->header[] = 'value3';

            expect($this->header->value())->toBe('value1, value2, value3');

        });

    });

    describe("->toString()", function() {

        it("returns the string representation of the header", function() {

            $this->header->name('Header-Name');
            $this->header[] = 'value1';
            $this->header[] = 'value2';
            $this->header[] = 'value3';

            expect((string) $this->header)->toBe('Header-Name: value1, value2, value3');

        });

    });

    describe("::parse()", function() {

        it("parses an header", function() {

            $header = Header::parse('Header-Name: value1, value2, value3');
            expect($header->name())->toBe('Header-Name');
            expect($header->to('array'))->toBe([
                'value1',
                'value2',
                'value3'
            ]);

        });

    });

});