<?php
namespace Lead\Net\Spec\Suite\Http\Psr7;

use InvalidArgumentException;
use Lead\Net\Http\Psr7\Uri;
use Lead\Net\Http\Response
;

describe("responseTrait", function() {

    describe("->getStatusCode()", function() {

        it("gets the status code", function() {

            $response = new Response();
            expect($response->getStatusCode())->toEqual(200);

        });

    });

    describe("->getReasonPhrase()", function() {

        it("gets the reason phrase code", function() {

            $response = new Response();
            expect($response->getReasonPhrase())->toEqual('OK');

        });

    });

    describe("->withStatus()", function() {

        it("creates a new response with the provided status code & reason", function() {

            $response = new Response();
            $new = $response->withStatus(404, 'Page Not Found');

            expect($response->getStatusCode())->toBe(200);
            expect($response->getReasonPhrase())->toBe('OK');
            expect($new)->not->toBe($response);
            expect($new->getStatusCode())->toBe(404);
            expect($new->getReasonPhrase())->toBe('Page Not Found');

        });

        it("creates a new response with the provided status code", function() {

            $response = new Response();
            $new = $response->withStatus(404);

            expect($response->getStatusCode())->toBe(200);
            expect($response->getReasonPhrase())->toBe('OK');
            expect($new)->not->toBe($response);
            expect($new->getStatusCode())->toBe(404);
            expect($new->getReasonPhrase())->toBe('Not Found');

        });

    });

});