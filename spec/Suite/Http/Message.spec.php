<?php
namespace Lead\Net\Spec\Suite\Http;

use InvalidArgumentException;
use Lead\Net\NetException;
use Lead\Net\Http\Message;
use Lead\Net\Headers;

describe("Message", function() {

    describe("->__construct()", function() {

        it("sets default values", function() {

            $message = new Message([
                'mime'    => 'text/plain',
                'charset' => 'UTF-8'
            ]);

            $headers = $message->headers();
            expect($headers)->toBeAnInstanceOf(Headers::class);
            expect((string) $headers['Content-Type'])->toBe('Content-Type: text/plain; charset=UTF-8');

        });

        it("parses passed headers", function() {

            $message = new Message(['headers' => [
                'User-Agent: Mozilla/5.0',
                'Cache-Control: no-cache'
            ]]);

            $expected =<<<EOD
User-Agent: Mozilla/5.0\r
Cache-Control: no-cache\r

EOD;

            expect(Headers::toHeader($message->headers()))->toBe($expected);

        });

    });

    describe("->chunkSize()", function() {

        it("gets/sets the chunk size", function() {

            $message = new Message();
            expect($message->chunkSize())->toBe(256);
            expect($message->chunkSize(512))->toBe($message);
            expect($message->chunkSize())->toBe(512);

        });

    });

    describe("->protocol()", function() {

        it("returns the protocol", function() {

            $message = new Message();
            expect($message->protocol())->toBe('HTTP/1.1');

        });
    });

    describe("->version()", function() {

        it("returns the protocol version", function() {

            $message = new Message();
            expect($message->version())->toBe('1.1');

        });

        it("gets/sets the protocol version", function() {

            $message = new Message();
            $message->version('1.0');
            expect($message->version())->toBe('1.0');

        });
    });

    describe("->mime()", function() {

        it("gets/sets the content type ", function() {

            $message = new Message();
            expect($message->mime('application/json'))->toBe($message);
            expect($message->mime())->toBe('application/json');

            expect($message->mime('application/csv'))->toBe($message);
            expect($message->mime())->toBe('application/csv');

        });

        it("returns the content type initialized using headers", function() {

            $message = new Message(['headers' => [
                'Content-Type: application/json; charset=UTF-8'
            ]]);
            expect($message->mime())->toBe('application/json');

            $headers = $message->headers();
            expect($headers['Content-Type']->value())->toBe('application/json; charset=UTF-8');

        });

        it("removes the content type ", function() {

            $message = new Message();
            expect($message->mime('application/json'))->toBe($message);
            expect($message->mime())->toBe('application/json');

            expect($message->mime(false))->toBe($message);
            expect($message->mime())->toBe(null);

        });

    });

    describe("->charset()", function() {

        it("gets/sets the content type charset", function() {

            $message = new Message(['headers' => [
                'Content-Type: application/json; charset=UTF-8'
            ]]);
            expect($message->charset())->toBe('UTF-8');
            expect($message->charset('utf-16'))->toBe($message);
            expect($message->charset())->toBe('UTF-16');

        });

        it("returns `null` when no charset has been defined", function() {

            $message = new Message();
            expect($message->charset())->toBe(null);

        });

    });

    describe("->headers()", function() {

        beforeEach(function() {
            $this->headers = <<<EOD
Custom-Header: Custom Value\r
Content-Type: text/html\r

EOD;
        });

        it("sets headers as a string", function() {

            $message = new Message(['headers' => $this->headers]);

            $headers = $message->headers();
            expect($headers->to('header'))->toBe($this->headers);

        });

        it("sets headers as an object", function() {

            $headers = new Headers();
            $headers->push($this->headers);

            $message = new Message(['headers' => $headers]);

            $headers = $message->headers();
            expect($headers->to('header'))->toBe($this->headers);

        });

    });

    describe("->set()", function() {

        it("endodes according to the Content-Type", function() {

            $message = new Message();
            $message->format("json");

            expect($message->set(""))->toBe($message);
            expect($message->body())->toBe('""');

            expect($message->set(['name' => 'value']))->toBe($message);
            expect($message->body())->toBe('{"name":"value"}');

        });

    });

    describe("->get()", function() {

        it("decodes according to the Content-Type", function() {

            $message = new Message();
            $message->format("json");

            expect($message->body('""'))->toBe($message);
            expect($message->get())->toBe("");

            expect($message->body('{"name":"value"}'))->toBe($message);
            expect($message->get())->toBe(['name' => 'value']);

        });

    });

    describe("->line()", function() {

        it("retuns an empty string", function() {

            $message = new Message(['body' => 'Body Message']);

            expect($message->line())->toBe('');

        });

    });

    describe("->body()", function() {

        it("gets the body string", function() {

            $message = new Message(['body' => 'Hello World!']);
            expect($message->body())->toBe('Hello World!');

        });

        it("sets the body using a string", function() {

            $message = new Message();
            $message->body('Hello World!');

            expect($message->body())->toBe('Hello World!');

        });

    });

    describe("->stream()", function() {

        it("gets the body stream", function() {

            $message = new Message(['body' => 'Hello World!']);
            $stream = $message->stream();
            expect((string) $stream)->toBe('Hello World!');

        });

    });

    describe("->export()", function() {

        it("returns the query", function() {

            $message = new Message([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 80,
                'username' => 'username',
                'password' => 'password',
                'path'     => 'index.php'
            ]);

            expect($message->export())->toEqual(['body' => $message->stream()]);

        });

    });

    describe("->to()", function() {

        it("exports in JSON", function() {

            $message = new Message();
            expect($message->body('{"name":"value"}'))->toBe($message);
            expect($message->to('json'))->toBe(['name' => 'value']);

        });

        it("throws an exception with unsupported format", function() {

            $closure = function() {
                $message = new Message();
                $message->to('abc');
            };

            expect($closure)->toThrow(new InvalidArgumentException("Unsupported format `abc`."));

        });

    });

    describe("->__toString", function() {

        it("casts to a string", function() {

            $message = new Message(['body' => 'Body Message']);

            expect((string) $message)->toBe('Body Message');

        });

    });
});
