<?php
namespace Lead\Net\Spec\Suite\Http\Psr7;

use InvalidArgumentException;
use Lead\Net\Http\Psr7\Uri;
use Lead\Net\Http\Request;

describe("RequestTrait", function() {

    describe("->getMethod()", function() {

        it("delegates to `->method()`", function() {

            $request = new Request();

            expect($request)->toReceive('method');

            $request->getMethod();

        });

    });

    describe("->withMethod()", function() {

        it("creates a new request with the provided method", function() {

            $request = new Request();
            $new = $request->withMethod('POST');

            expect($request->getMethod())->toBe('GET');
            expect($new)->not->toBe($request);
            expect($new->getMethod())->toBe('POST');

        });

    });

    describe("->getRequestTarget()", function() {

        it("delegates to `->path()`", function() {

            $request = new Request();

            expect($request)->toReceive('requestTarget');

            $request->getRequestTarget();

        });

    });

    describe("->withRequestTarget()", function() {

        it("creates a new request with the provided request target url", function() {

            $request = new Request();
            $new = $request->withRequestTarget('https://www.domain.com:8000/foo?bar=baz#quz');

            expect($new)->not->toBe($request);
            expect($new->getRequestTarget())->toBe('https://www.domain.com:8000/foo?bar=baz#quz');

        });

        it("creates a new request with the provided request target authority", function() {

            $request = new Request();
            $new = $request->withRequestTarget('username:password@www.domain.com:8000');

            expect($new)->not->toBe($request);
            expect($new->getRequestTarget())->toBe('username:password@www.domain.com:8000');

        });

        it("creates a new request with the provided request target asterisk", function() {

            $request = new Request();
            $new = $request->withRequestTarget('*');

            expect($new)->not->toBe($request);
            expect($new->getRequestTarget())->toBe('*');

        });

        it("creates a new request with the provided request target origin", function() {

            $request = new Request();
            $new = $request->withRequestTarget('/index.php?foo=bar#baz');

            expect($new)->not->toBe($request);
            expect($new->getRequestTarget())->toBe('/index.php?foo=bar#baz');

        });

    });

    describe("->getUri()", function() {

        it("returns the request URI", function() {

            $request = Request::create('GET', 'https://username:password@www.domain.com:8000/foo?bar=baz#quz');
            $uri = $request->getUri();
            expect($uri)->toBeAnInstanceOf('Psr\Http\Message\UriInterface');
            expect((string) $uri)->toBe('https://username:password@www.domain.com:8000/foo?bar=baz#quz');

        });

    });

    describe("->withUri()", function() {

        it("creates a new request with the provided URI", function() {

            $request = new Request();
            $new = $request->withUri(new Uri('https://username:password@www.domain.com:8000/foo?bar=baz#quz'));
            expect($new)->not->toBe($request);

            $uri = $new->getUri();
            expect($uri)->toBeAnInstanceOf('Psr\Http\Message\UriInterface');
            expect((string) $uri)->toBe('https://username:password@www.domain.com:8000/foo?bar=baz#quz');
            expect($new->getHeaderLine('Host'))->toBe('www.domain.com:8000');

        });

        it("creates a new request with the provided URI and preserve the Host header", function() {

            $request = new Request();
            $new = $request->withUri(new Uri('https://username:password@www.domain.com:8000/foo?bar=baz#quz'), true);
            expect($new)->not->toBe($request);

            $uri = $new->getUri();
            expect($uri)->toBeAnInstanceOf('Psr\Http\Message\UriInterface');
            expect((string) $uri)->toBe('https://username:password@localhost:8000/foo?bar=baz#quz');
            expect($new->getHeaderLine('Host'))->toBe('localhost');

        });

    });

});
