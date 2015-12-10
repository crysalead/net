<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Net\NetException;
use Lead\Net\Http\Headers;

use Kahlan\Plugin\Stub;

function Message($config = []) {
    $message = Stub::classname(['extends' => 'Lead\Net\Http\Message']);
    return new $message($config);
}

describe("Message", function() {

    describe("->__construct()", function() {

        it("sets default values", function() {

            $message = Message([
                'type'     => 'text/plain',
                'encoding' => 'UTF-8'
            ]);

            $headers = $message->headers();
            expect($headers)->toBeAnInstanceOf(Headers::class);
            expect((string) $headers['Content-Type'])->toBe('Content-Type: text/plain; charset=UTF-8');

        });

        it("parses passed headers", function() {

            $message = Message(['headers' => [
                'User-Agent: Mozilla/5.0',
                'Cache-Control: no-cache'
            ]]);

            $expected =<<<EOD
User-Agent: Mozilla/5.0\r
Cache-Control: no-cache\r
\r

EOD;

            expect(Headers::toHeader($message->headers()))->toBe($expected);

        });

    });

    describe("->protocol()", function() {

        it("returns the protocol", function() {

            $message = Message();
            expect($message->protocol())->toBe('HTTP/1.1');

        });
    });

    describe("->version()", function() {

        it("returns the protocol version", function() {

            $message = Message();
            expect($message->version())->toBe('1.1');

        });

        it("gets/sets the protocol version", function() {

            $message = Message();
            $message->version('1.0');
            expect($message->version())->toBe('1.0');

        });
    });

    describe("->type()", function() {

        it("gets/sets the content type ", function() {

            $message = Message();
            expect($message->type('application/json'))->toBe($message);
            expect($message->type())->toBe('application/json');

            expect($message->type('application/csv'))->toBe($message);
            expect($message->type())->toBe('application/csv');

        });

        it("returns the content type initialized using headers", function() {

            $message = Message(['headers' => [
                'Content-Type: application/json; charset=UTF-8'
            ]]);
            expect($message->type())->toBe('application/json');
            expect($message->headers()['Content-Type']->value())->toBe('application/json; charset=UTF-8');

        });

        it("removes the content type ", function() {

            $message = Message();
            expect($message->type('application/json'))->toBe($message);
            expect($message->type())->toBe('application/json');

            expect($message->type(false))->toBe($message);
            expect($message->type())->toBe(null);

        });

    });

    describe("->encoding()", function() {

        it("gets/sets the content type charset encoding", function() {

            $message = Message(['headers' => [
                'Content-Type: application/json; charset=UTF-8'
            ]]);
            expect($message->encoding())->toBe('UTF-8');
            expect($message->encoding('utf-16'))->toBe($message);
            expect($message->encoding())->toBe('UTF-16');

        });

        it("returns `null` when no encoding has been defined", function() {

            $message = Message();
            expect($message->encoding())->toBe(null);

        });

        it("throws an exception when no Content-Type has been defined", function() {

            $closure = function() {
                $message = Message();
                $message->encoding('UTF-8');
            };

            expect($closure)->toThrow(new NetException("Can't set a charset with no valid Content-Type defined."));

        });

    });

    describe("->headers()", function() {

        beforeEach(function() {
            $this->headers = <<<EOD
Custom-Header: Custom Value\r
\r

EOD;
        });

        it("sets headers as a string", function() {

            $message = Message(['headers' => $this->headers]);
            expect($message->headers()->to('header'))->toBe($this->headers);

        });

        it("sets headers as an object", function() {

            $headers = new Headers();
            $headers->add($this->headers);

            $message = Message(['headers' => $headers]);
            expect($message->headers()->to('header'))->toBe($this->headers);

        });

    });

    describe("->body()", function() {

        it("endodes according to the Content-Type", function() {

            $message = Message();
            $message->type("application/json");

            expect($message->body(""))->toBe($message);
            expect($message->plain())->toBe('""');

            expect($message->body(['name' => 'value']))->toBe($message);
            expect($message->plain())->toBe('{"name":"value"}');

        });

        it("decodes according to the Content-Type", function() {

            $message = Message();
            $message->type("application/json");

            expect($message->plain('""'))->toBe($message);
            expect($message->body())->toBe("");

            expect($message->plain('{"name":"value"}'))->toBe($message);
            expect($message->body())->toBe(['name' => 'value']);

        });

    });

});
