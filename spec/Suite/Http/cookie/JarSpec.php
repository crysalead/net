<?php
namespace Lead\Net\Spec\Suite\Http\Cookie;

use Exception;
use Lead\Net\Http\Cookie\Jar;
use Lead\Net\Http\Cookie\Cookie;
use Lead\Net\Http\Cookie\SetCookies;

describe("Jar", function() {

	describe("::toJar()", function() {

        it('exports cookies into a JAR string', function() {

        	$setCookies = new SetCookies();
            $setCookies['foo1'] = 'bar1';
            $setCookies['foo2'] = 'bar2';
            $setCookies['foo3'] = 'bar3';

            expect(Jar::toJar($setCookies))->toBe(join("\n", [
                "\tFALSE\t/\tFALSE\t0\tfoo1\tbar1",
                "\tFALSE\t/\tFALSE\t0\tfoo2\tbar2",
                "\tFALSE\t/\tFALSE\t0\tfoo3\tbar3",
                ""
            ]));

        });

        it("exports a custom cookie into an JAR string", function() {

        	$nextYear = date('Y') + 1;
            $expires = strtotime("{$nextYear}-12-25 00:00:00 UTC");

            $cookie = new Cookie('the cookie value', [
                'expires'  => $expires,
                'path'     => '/blog',
                'domain'   => '.domain.com',
                'max-age'  => null,
                'secure'   => true,
                'httponly' => true
            ]);

            $this->expect(Jar::toJar(['mycookie' => $cookie]))->toBe(
                "#HttpOnly_.domain.com\tFALSE\t/blog\tTRUE\t{$expires}\tmycookie\tthe cookie value\n"
            );

        });

        it("throws an exception with invalid names", function() {

            foreach (str_split("=,; \t\r\n\013\014") as $invalid) {

                $closure = function() use ($invalid) {
                    $cookie = new Cookie('foo');
                    Jar::toJar(["ab{$invalid}ba" => $cookie]);
                };
                expect($closure)->toThrow(new Exception("Invalid cookie name `'ab{$invalid}ba'`."));

            }

        });

    });

    describe("::parse()", function() {

        it("parses a cookie from an JAR string", function() {

            $data = Jar::parse(
                "#HttpOnly_.domain.com\tFALSE\t/blog\tTRUE\t1419465600\tmycookie\tthe cookie value"
            );

            $this->expect($data)->toBe([
                'httponly' => true,
                'domain' => '.domain.com',
                'path' => '/blog',
                'secure' => true,
                'expires' => 1419465600,
                'name' => 'mycookie',
                'value' => 'the cookie value'
            ]);

        });

        it("throws an exception with invalid format", function() {

            $closure = function() {
                $data = Jar::parse(
                    "#HttpOnly_.domain.com\tFALSE\t/blog\tTRUE\t1419465600\tmycookie"
                );
            };
            expect($closure)->toThrow(new Exception("Invalid cookie JAR format."));

        });

    });

});