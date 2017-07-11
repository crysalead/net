<?php
namespace Lead\Net\Spec\Suite;

use Exception;
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

    describe("->__toString", function() {

        it("casts to a string", function() {

            $message = new Message(['body' => 'Body Message']);

            expect((string) $message)->toBe('Body Message');

        });

    });

});