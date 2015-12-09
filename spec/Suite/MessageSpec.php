<?php
namespace Lead\Net\Spec\Suite;

use Exception;
use InvalidArgumentException;
use Lead\Net\Message;
use Lead\Net\Headers;

describe("Message", function() {

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

        it("sets the body using a stream", function() {

            $message = new Message();
            $message->stream('Hello World!');

            $stream = $message->stream();
            expect((string) $stream)->toBe('Hello World!');

        });

    });

    describe("formats", function() {

        it("gets registered formats", function() {

            Message::formats('json', function() {});
            expect(array_keys(Message::formats()))->toBe(['array', 'json']);

        });

        it("removes a specific registered formats", function() {

            Message::formats('json', function() {});
            Message::formats('json', false);

            expect(array_keys(Message::formats()))->toBe(['array']);

        });

        it("removes all registered formats", function() {

            Message::formats('json', function() {});
            Message::formats(false);

            expect(array_keys(Message::formats()))->toBe(['array']);

        });
    });

    describe("->data()", function() {

        it("delegates to `->to('array')`", function() {

            $message = new Message();

            expect($message)->toReceive('to')->with('array');

            $message->data();

        });

    });

    describe("->to('array')", function() {

        it("returns the query", function() {

            $message = new Message([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 80,
                'username' => 'username',
                'password' => 'password',
                'path'     => 'index.php'
            ]);

            expect($message->to('array'))->toEqual(['body' => $message->stream()]);

        });

        it("exports using a closure", function() {

            $message = new Message([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 80,
                'username' => 'username',
                'password' => 'password',
                'path'     => 'index.php'
            ]);

            $result = $message->to(function($message) {
                return [
                    'body' => $message->stream()
                ];
            });
            expect($message->to('array'))->toEqual(['body' => $message->stream()]);

        });

        it("throws an exception with unsupported format", function() {

            $closure = function() {
                $message = new Message();
                $message->to('xml');
            };

            expect($closure)->toThrow(new InvalidArgumentException("Unsupported format `xml`."));

        });

    });

    describe("->__toString", function() {

        it("casts to a string", function() {

            $message = new Message([
                'body'   => 'Body Message'
            ]);

            expect((string) $message)->toBe("Body Message");

        });

    });

});