<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Net\NetException;
use Lead\Net\Http\Auth;

describe("Auth", function() {

    describe("::encode()", function() {

        it("encodes a basic auth", function() {

            $username = 'willy';
            $password = 'abcdef';
            $response = base64_encode("{$username}:{$password}");
            $expected = compact('username', 'response');
            $result = Auth::encode($username, $password);
            expect($result)->toEqual($expected);

        });

        it("encodes a digest auth", function() {

            $username = 'willy';
            $password = 'abcdef';
            $nc = '00000001';
            $cnonce = md5(time());
            $user = md5("willy:app:abcdef");
            $nonce = "4bca0fbca7bd0:{$nc}:{$cnonce}:auth";
            $req = md5("GET:/http_auth");
            $response = md5("{$user}:{$nonce}:{$req}");

            $data = [
                'realm'  => 'app',
                'method' => 'GET',
                'uri'    => '/http_auth',
                'qop'    => 'auth',
                'nonce'  => '4bca0fbca7bd0',
                'opaque' => 'd3fb67a7aa4d887ec4bf83040a820a46'
            ];
            $expected = $data + compact('username', 'response', 'nc', 'cnonce');
            $result = Auth::encode($username, $password, $data);
            expect($result)->toEqual($expected);

        });

    });

    describe("::header()", function() {

        it("generates a basic auth header", function() {

            $username = 'willy';
            $password = 'abcdef';
            $response = base64_encode("{$username}:{$password}");
            $data = Auth::encode($username, $password);
            $expected = "Basic " . $response;
            $result = Auth::header($data);
            expect($result)->toBe($expected);

        });

        it("generates a digest auth header", function() {

            $username = 'willy';
            $password = 'abcdef';
            $nc = '00000001';
            $cnonce = md5(time());
            $user = md5("willy:app:abcdef");
            $nonce = "4bca0fbca7bd0:{$nc}:{$cnonce}:auth";
            $req = md5("GET:/http_auth");
            $hash = md5("{$user}:{$nonce}:{$req}");

            $data = [
                'realm'  => 'app',
                'method' => 'GET',
                'uri'    => '/http_auth',
                'qop'    => 'auth',
                'nonce'  => '4bca0fbca7bd0',
                'opaque' => 'd3fb67a7aa4d887ec4bf83040a820a46'
            ];
            $data = Auth::encode($username, $password, $data);
            $header = Auth::header($data);
            expect($header)->toMatch('/Digest/');
            preg_match('/response="(.*?)"/', $header, $matches);
            list($match, $response) = $matches;

            $expected = $hash;
            $result = $response;
            expect($result)->toBe($expected);
        });

        it("throws an exception with invalid auth data", function() {

            $closure = function() {
                Auth::header([]);
            };

            expect($closure)->toThrow(new NetException("Can't create Authorization headers from an empty response."));

        });

    });

    describe("::decode()", function() {

        it("decodes a header", function() {

            $header = 'qop="auth",nonce="4bca0fbca7bd0",';
            $header .= 'nc=00000001,cnonce="95b2cd1e179bf5414e52ed62811481cf",';
            $header .= 'uri="/http_auth",realm="app",';
            $header .= 'opaque="d3fb67a7aa4d887ec4bf83040a820a46",username="willy",';
            $header .= 'response="04d7d878c67f289f37e553d2025e3a52"';

            $expected = [
                'qop'      => 'auth',
                'nonce'    => '4bca0fbca7bd0',
                'nc'       => '00000001',
                'cnonce'   => '95b2cd1e179bf5414e52ed62811481cf',
                'uri'      => '/http_auth',
                'realm'    => 'app',
                'opaque'   => 'd3fb67a7aa4d887ec4bf83040a820a46',
                'username' => 'willy',
                'response' => '04d7d878c67f289f37e553d2025e3a52'
            ];
            $result = Auth::decode($header);
            expect($result)->toEqual($expected);

        });

    });

});