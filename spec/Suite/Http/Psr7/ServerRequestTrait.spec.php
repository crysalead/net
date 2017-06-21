<?php
namespace Lead\Net\Spec\Suite\Http\Psr7;

use InvalidArgumentException;
use Lead\Net\Http\Psr7\Uri;
use Lead\Net\Http\Cgi\Request;

describe("ServerRequestTrait", function() {

    beforeAll(function() {
        $this->globalNames =['_GET', '_POST', '_SERVER'];
        $this->oldEnv = [];
        foreach ($this->globalNames as $varname) {
            $this->oldEnv[$varname] = $GLOBALS[$varname];
            unset($GLOBALS[$varname]);
        }
    });

    afterEach(function() {
        foreach ($this->globalNames as $varname) {
            $GLOBALS[$varname] = $this->oldEnv[$varname];
        }
    });

    describe("->getServerParams()", function() {

        beforeEach(function() {
            defineGlobals();
        });


        it("gets params`", function() {

            defineGlobals([
                'SERVER' => [
                    'REQUEST_URI' => '/base/path/app?get=value'
                ]
            ]);

            $request = Request::ingoing();

            expect($request->getServerParams())->toBe([
                'REQUEST_URI' => '/base/path/app?get=value',
                'SCRIPT_NAME' => '/base/path/webroot/index.php',
                'PHP_SAPI' => 'cli',
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'CONTENT_TYPE' => 'text/html; charset=utf-8',
                'HTTP_HOST' => 'localhost',
                'SERVER_PORT' => 80,
                'REQUEST_METHOD' => 'GET'
            ]);

        });

    });

    describe("->getCookieParams()/->withCookieParams()", function() {

        it("gets/sets cookies", function() {

            $request = new Request();

            $request = $request->withCookieParams([
                'foo' => 'bar',
                'bar' => 'foo'
            ]);

            expect($request->getCookieParams())->toEqual([
                'foo' => 'bar',
                'bar' => 'foo'
            ]);

        });

    });

    describe("->getQueryParams()->withQueryParams()", function() {

        it("gets/sets the query string", function() {

            $expected = "?param=value&param1=value1";
            $request = new Request();
            expect($request = $request->withQueryParams(['param' => 'value', 'param1' => 'value1']))->toBe($request);
            expect($request->getQueryParams())->toBe(['param' => 'value', 'param1' => 'value1']);

            expect($request->requestTarget())->toBe('/?param=value&param1=value1');

        });

    });

    describe("->getAttributes()", function() {

        it("gets params", function() {

            $request = new Request([
                'params' => [
                    'param' => 'value', 'param1' => 'value1'
                ]
            ]);
            expect($request->getAttributes())->toBe(['param' => 'value', 'param1' => 'value1']);

        });

    });

    describe("->getAttribute()->withAttribute()", function() {

        it("gets/sets a param", function() {

            $request = new Request();
            expect($request = $request->withAttribute('param', 'value'))->toBe($request);
            expect($request->getAttribute('param'))->toBe('value');

        });

    });

    describe("->withoutAttribute()->withAttribute()", function() {

        it("deletes a param", function() {

            $request = new Request([
                'params' => [
                    'param' => 'value', 'param1' => 'value1'
                ]
            ]);
            $request = $request->withoutAttribute('param');
            expect($request->getAttributes())->toBe(['param1' => 'value1']);

        });

    });

    describe("->getParsedBody()->withParsedBody()", function() {

        it("decodes/endodes according to the Content-Type", function() {

            $message = new Request();
            $message->format("json");

            expect($message = $message->withParsedBody(""))->toBe($message);
            expect($message->getParsedBody())->toBe("");

            expect($message = $message->withParsedBody(['name' => 'value']))->toBe($message);
            expect($message->getParsedBody())->toBe(['name' => 'value']);

        });

    });

});
