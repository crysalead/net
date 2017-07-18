<?php
namespace Lead\Net\Spec\Suite\Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Lead\Net\Part;
use Lead\Net\Http\Psr7\Uri;
use Lead\Net\Http\Request;

describe("MessageTrait", function() {

    describe("->getProtocolVersion()", function() {

        it("delegates to `->version()`", function() {

            $request = new Request();

            expect($request)->toReceive('version');

            $request->getProtocolVersion();

        });

    });

    describe("->withProtocolVersion()", function() {

        it("creates a new request with the provided protocol version", function() {

            $request = new Request();
            $new = $request->withProtocolVersion('2.0');

            expect($request->getProtocolVersion())->toBe('1.1');
            expect($new)->not->toBe($request);
            expect($new->getProtocolVersion())->toBe('2.0');

        });

    });

    describe("->getHeaders()", function() {

        it("returns headers", function() {

            $headers = [
                'Date: Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type: text/html; charset=UTF-8',
                'Vary: Accept-Encoding, Cookie, User-Agent',
            ];

            $request = new Request([
                'headers' => $headers,
                'data'    => 'Body Message'
            ]);

            expect($request->getHeaders())->toEqual([
                'Host'         => ['localhost'],
                'Connection'   => ['Close'],
                'User-Agent'   => ['Mozilla/5.0'],
                'Date'         => ['Thu', '25 Dec 2014 00:00:00 GMT'],
                'Vary'         => ['Accept-Encoding', 'Cookie', 'User-Agent'],
                'Content-Type' => ['text/html; charset=UTF-8']
            ]);

        });

    });

    describe("->hasHeader()", function() {

        it("checks existing headers", function() {

            $request = new Request([
                'headers' => ['Content-Type: text/html; charset=UTF-8'],
                'data'    => 'Body Message'
            ]);


            expect($request->hasHeader('Content-Type'))->toBe(true);
            expect($request->hasHeader('CONTENT-TYPE'))->toBe(true);
            expect($request->hasHeader('Date'))->toBe(false);

        });

    });

    describe("->getHeader()", function() {

        it("gets an header", function() {

            $request = new Request([
                'headers' => ['Content-Type: text/html; charset=UTF-8'],
                'data'    => 'Body Message'
            ]);


            expect($request->getHeader('Content-Type'))->toBe(['text/html; charset=UTF-8']);
            expect($request->getHeader('CONTENT-TYPE'))->toBe(['text/html; charset=UTF-8']);

        });

    });

    describe("->getHeaderLine()", function() {

        it("gets an header", function() {

            $request = new Request([
                'headers' => ['Content-Type: text/html; charset=UTF-8'],
                'data'    => 'Body Message'
            ]);


            expect($request->getHeaderLine('Content-Type'))->toBe('text/html; charset=UTF-8');
            expect($request->getHeaderLine('CONTENT-TYPE'))->toBe('text/html; charset=UTF-8');

        });

    });

    describe("->withHeader()", function() {

        it("creates a new request with the provided protocol version", function() {

            $request = new Request();
            $new = $request->withHeader('Content-Type', 'application/json');

            expect($request->getHeaderLine('Content-Type'))->toBe('');
            expect($new)->not->toBe($request);
            expect($new->getHeaderLine('Content-Type'))->toBe('application/json');

        });

    });

    describe("->withAddedHeader()", function() {

        it("creates a new request with the provided protocol version", function() {

            $request = new Request([
                'headers' => ['Vary: Accept-Encoding, Cookie'],
            ]);
            $new = $request->withAddedHeader('Vary', 'User-Agent');

            expect($request->getHeaderLine('Vary'))->toBe('Accept-Encoding, Cookie');
            expect($new)->not->toBe($request);
            expect($new->getHeaderLine('Vary'))->toBe('Accept-Encoding, Cookie, User-Agent');

        });

    });

    describe("->withoutHeader()", function() {

        it("creates a new request with the provided protocol version", function() {

            $request = new Request([
                'headers' => ['Content-Type: text/html; charset=UTF-8'],
            ]);
            $new = $request->withoutHeader('Content-Type');

            expect($request->hasHeader('Content-Type'))->toBe(true);
            expect($new)->not->toBe($request);
            expect($new->hasHeader('Content-Type'))->toBe(false);

        });

    });

    describe("->getBody()", function() {

        it("gets the stream body", function() {

            $request = new Request(['data' => 'Body Message']);
            $body = $request->getBody();
            expect($body)->toBeAnInstanceOf(StreamInterface::class);
            expect((string) $body)->toBe('Body Message');

        });

    });

    describe("->withBody()", function() {

        it("creates a new request with the provided stream", function() {

            $request = new Request(['data' => 'Body Message']);

            $stream = new Part('New Message');
            $new = $request->withBody($stream);

            expect($request->getBody()->toString())->toBe('Body Message');
            expect($new)->not->toBe($request);
            expect((string) $new->getBody())->toBe('New Message');

        });

    });

});
