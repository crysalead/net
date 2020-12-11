<?php
namespace Lead\Net\Spec\Suite;

use Exception;
use Lead\Net\NetException;
use Lead\Net\Header;
use Lead\Net\Headers;
use Lead\Net\Http\Cookie\Cookies;

describe("Headers", function() {

    beforeEach(function() {
        $this->headers = new Headers([
            'cookies' => new Cookies()
        ]);
    });

    describe("->offsetGet()/->offsetSet()", function() {

        it("sets an header", function() {

            $this->headers['Content-Type'] = 'text/plain';

            expect($this->headers['Content-Type']->value())->toBe('text/plain');
            expect($this->headers['Content-Type']->to('array'))->toBe(['text/plain']);

        });

        it("sets an header instance", function() {

            $this->headers['Content-Type'] = new Header('text/plain');

            expect($this->headers['Content-Type']->value())->toBe('text/plain');
            expect($this->headers['Content-Type']->to('array'))->toBe(['text/plain']);

        });

        it("is case insensitive", function() {

            $this->headers['content-type'] = 'text/plain';
            $this->headers['CONTENT-TYPE'] = 'application/json';

            expect($this->headers['content-type']->value())->toBe('application/json');
            expect($this->headers['CONTENT-TYPE']->to('array'))->toBe(['application/json']);

        });

        it("sets multi values header", function() {

            $this->headers['Accept'] = 'text/html;q=1.0';
            $this->headers['Accept'][] = '*/*;q=0.1';

            expect($this->headers['Accept']->value())->toBe('text/html;q=1.0, */*;q=0.1');
            expect($this->headers['Accept']->to('array'))->toBe([
                'text/html;q=1.0',
                '*/*;q=0.1'
            ]);

        });

        it("overrides multi values header", function() {

            $this->headers['Accept'] = 'text/html;q=1.0';
            $this->headers['Accept'][] = '*/*;q=0.1';

            $this->headers['Accept'] = 'application/json';
            expect($this->headers['Accept']->value())->toBe('application/json');
            expect($this->headers['Accept']->to('array'))->toBe(['application/json']);

        });

        it("overrides a value inside a loop", function() {

            $this->headers['Accept'] = 'application/vnd.api+json';
            $this->headers['Authorization'] = ' Bearer eyJ0eXAiOiJKV1QiLCJhbGc...';
            $this->headers['Content-Type'] = 'application/vnd.api+json';

            foreach ($this->headers as $key => $header) {
                if ($key === 'Authorization') {
                    $this->headers[$key] = '<FILTERED DATA>';
                }
            }
            expect($this->headers->data())->toBe([
                'Accept: application/vnd.api+json',
                'Authorization: <FILTERED DATA>',
                'Content-Type: application/vnd.api+json'
            ]);

        });

        it("throws an exception with an empty name", function() {

            $closure = function() {
                $this->headers[] = 'value';
            };

            expect($closure)->toThrow(new Exception("Error, invalid header name, can't be empty."));

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

        it("exports headers as a key/value array", function() {

            $this->headers['Content-Type'] = 'text/plain';
            $this->headers['X-Custom-ABC'] = 'abc';
            $this->headers['Accept'][] = 'text/html;q=1.0';
            $this->headers['Accept'][] = '*/*;q=0.1';
            $this->headers['Cookie'][] = 'sessionid=123';

            expect($this->headers->data())->toBe([
                'Content-Type: text/plain',
                'X-Custom-ABC: abc',
                'Accept: text/html;q=1.0, */*;q=0.1',
                'Cookie: sessionid=123'
            ]);

        });

    });

    describe("::toHeader()", function() {

        it("exports headers as a string", function() {

            $this->headers['Content-Type'] = 'text/plain';
            $this->headers['X-Custom-ABC'] = 'abc';
            $this->headers['Accept'][] = 'text/html;q=1.0';
            $this->headers['Accept'][] = '*/*;q=0.1';
            $this->headers['Cookie'][] = 'sessionid=123';

            $expected = <<<EOD
Content-Type: text/plain\r
X-Custom-ABC: abc\r
Accept: text/html;q=1.0, */*;q=0.1\r
Cookie: sessionid=123
EOD;

            expect(Headers::toHeader($this->headers))->toBe($expected);

        });

    });

    describe("->push()", function() {

        beforeEach(function() {

            $this->expected = <<<EOD
Date: Thu, 25 Dec 2014 00:00:00 GMT\r
Content-Type: text/html; charset=UTF-8\r
Vary: Accept-Encoding, Cookie, User-Agent
EOD;
        });

        it("pushes an header", function() {

            $this->headers->push('Content-Type', 'text/plain');
            expect($this->headers['Content-Type']->data())->toBe(['text/plain']);

        });

        it("pushes an header instance", function() {

            $this->headers->push('Content-Type', new Header(['data' => 'text/plain']));

            expect($this->headers['Content-Type']->value())->toBe('text/plain');
            expect($this->headers['Content-Type']->to('array'))->toBe(['text/plain']);

        });

        it("pushes an header using a plain string definition", function() {

            $this->headers->push('Content-Type: text/plain');
            expect($this->headers['Content-Type']->data())->toBe(['text/plain']);

        });

        it("pushes a collection of headers from an header string", function() {

            $headers = new Headers();
            $headers->push($this->expected);

            expect($headers->to('header'))->toBe($this->expected);

        });

        it("pushes a collection of headers from an array of headers", function() {

            $headers = new Headers();
            $headers->push([
                'Date: Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type: text/html; charset=UTF-8',
                'Vary: Accept-Encoding, Cookie, User-Agent'
            ]);

            expect($headers->to('header'))->toBe($this->expected);

        });

        it("pushes a collection of headers from an array of key/value", function() {

            $headers = new Headers();
            $headers->push([
                'Date'         => 'Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type' => 'text/html; charset=UTF-8',
                'Vary'         => ['Accept-Encoding', 'Cookie', 'User-Agent']
            ]);

            expect($headers->to('header'))->toBe($this->expected);

        });

        it("pushes a collection of header instances", function() {

            $headers = new Headers();
            $headers->push([
                new Header('Date', 'Thu, 25 Dec 2014 00:00:00 GMT'),
                new Header('Content-Type','text/html; charset=UTF-8'),
                new Header('Vary', ['Accept-Encoding', 'Cookie', 'User-Agent'])
            ]);

            expect($headers->to('header'))->toBe($this->expected);

        });

        it("overrides an header and its letter case definition", function() {

            $this->headers->push('Content-Type: text/plain');
            expect((string) $this->headers['Content-Type'])->toBe('Content-Type: text/plain');

            $this->headers->push('CONTENT-TYPE: application/json');
            expect((string) $this->headers['Content-Type'])->toBe('CONTENT-TYPE: application/json');

        });

        it("throws an exception for invalid HTTP headers", function() {

            $closure = function() {
                $this->headers->push('HTTP/1.1 200 OK');
            };

            expect($closure)->toThrow(new NetException("Invalid header: `'HTTP/1.1 200 OK'`."));

        });

    });

    describe("->prepend()", function() {

        it("prepends an header", function() {

            $this->headers->push('Content-Type', 'text/plain');
            $this->headers->prepend('Host', 'localhost');
            expect((string) $this->headers)->toBe("Host: localhost\r\nContent-Type: text/plain");

        });

    });

    describe("->clear()", function() {

        it("clears headers", function() {

            $this->headers['Content-Type'] = 'text/plain';
            expect(isset($this->headers['Content-Type']))->toBe(true);

            $this->headers->clear();
            expect(isset($this->headers['Content-Type']))->toBe(false);
        });

    });

    describe("->__toString()", function() {

        it("casts in string", function() {

            $header = <<<EOD
Date: Thu, 25 Dec 2014 00:00:00 GMT\r
Content-Type: text/html; charset=UTF-8\r
Vary: Accept-Encoding, Cookie, User-Agent
EOD;

            $headers = new Headers();
            $headers->push($header);
            expect((string) $headers)->toBe($header);

        });

    });

    describe("->__clone", function() {

        it("clones headers", function() {

            $headers = new Headers();
            $headers['Content-Type'] = 'text/html';

            $new = clone $headers;
            expect($headers['Content-Type'])->not->toBe($new['Content-Type']);
            expect($headers['Content-Type']->value())->toBe($new['Content-Type']->value());

        });

    });

});