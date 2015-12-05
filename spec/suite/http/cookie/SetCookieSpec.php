<?php
namespace net\http\spec\suite\cookie;

use Exception;
use net\http\cookie\SetCookie;

describe("SetCookie", function() {

    describe("->__construct()", function() {

        it("sets default values", function() {

            $cookie = new SetCookie('foo');

            $this->expect($cookie->data())->toBe([
                'value'    => 'foo',
                'expires'  => null,
                'path'     => '/',
                'domain'   => null,
                'max-age'  => null,
                'secure'   => false,
                'httponly' => false
            ]);

        });

    });

    describe("->domain()", function() {

        it("gets the Domain", function() {

            $cookie = new SetCookie('foo', ['domain' => '.foo.com']);

            $this->expect($cookie->domain())->toBe('.foo.com');

        });

        it("sets the Domain", function() {

            $cookie = new SetCookie('foo');
            $cookie->domain('.bar.com');

            $this->expect($cookie->domain())->toBe('.bar.com');

        });

        it("throws an exception with an invalid Domain", function() {

            $closure = function() {
                $cookie = new SetCookie('foo', ['domain' => 'localhost']);
            };
            expect($closure)->toThrow(new Exception("Invalid Domain `'localhost'`, at least two `'.'` are required."));

        });

    });

    describe("->path()", function() {

        it("gets the Path", function() {

            $cookie = new SetCookie('foo', ['path' => '/home']);

            $this->expect($cookie->path())->toBe('/home');

        });

        it("sets the Path", function() {

            $cookie = new SetCookie('foo');
            $cookie->path('/blog');
            $this->expect($cookie->path())->toBe('/blog');

        });

        it("throws an exception with an invalid Path", function() {

            $closure = function() {
                $cookie = new SetCookie('foo', ['path' => 'noleadingslash']);
            };
            expect($closure)->toThrow(new Exception("Invalid Path `'noleadingslash'`, non empty and leading slash are required."));

        });

    });

    describe("->expires()", function() {

        it("gets the default Expires", function() {

            $cookie = new SetCookie('foo');

            $this->expect($cookie->expires())->toBe(0);

        });

        it("gets the Expires", function() {

            $cookie = new SetCookie('foo', ['expires' => strtotime('2014-12-25 00:00:00 UTC')]);

            $this->expect($cookie->expires())->toBe(strtotime('2014-12-25 00:00:00 UTC'));

        });

        it("sets the Expires with a timestamp", function() {

            $cookie = new SetCookie('foo');
            $cookie->expires(strtotime('2014-12-25 00:00:00 UTC'));

            $this->expect($cookie->expires())->toBe(strtotime('2014-12-25 00:00:00 UTC'));

        });

        it("sets the Expires with a string", function() {

            $cookie = new SetCookie('foo');
            $cookie->expires('2014-12-25 00:00:00 UTC');

            $this->expect($cookie->expires())->toBe(strtotime('2014-12-25 00:00:00 UTC'));

        });

    });

    describe("->expired()", function() {

        it("checks if the SetCookie expired", function() {

            $cookie = new SetCookie('foo', ['expires' => strtotime('+1 day')]);
            $this->expect($cookie->expired())->toBe(false);

            $cookie = new SetCookie('foo', ['expires' => strtotime('-1 day')]);
            $this->expect($cookie->expired())->toBe(true);

        });

        it("checks if the SetCookie expires at the end of session", function() {

            $cookie = new SetCookie('foo', ['expires' => 0]);
            $this->expect($cookie->expired())->toBe(false);
            $this->expect($cookie->expired(true))->toBe(true);

        });

    });

    describe("->maxAge()", function() {

        it("gets the Max-Age", function() {

            $cookie = new SetCookie('foo', ['max-age' => 100]);

            $this->expect($cookie->maxAge())->toBe(100);

        });

        it("sets the Max-Age", function() {

            $cookie = new SetCookie('foo');
            $cookie->maxAge(100);
            $this->expect($cookie->maxAge())->toBe(100);

        });

    });

    describe("->secure()", function() {

        it("gets the Secure", function() {

            $cookie = new SetCookie('foo', ['secure' => true]);

            $this->expect($cookie->secure())->toBe(true);

        });

        it("sets the Secure", function() {

            $cookie = new SetCookie('foo');
            $cookie->secure(true);

            $this->expect($cookie->secure())->toBe(true);

        });

    });

    describe("->httpOnly()", function() {

        it("gets the HttpOnly", function() {

            $cookie = new SetCookie('foo', ['httponly' => true]);

            $this->expect($cookie->httpOnly())->toBe(true);

        });

        it("sets the HttpOnly", function() {

            $cookie = new SetCookie('foo');
            $cookie->httpOnly(true);

            $this->expect($cookie->httpOnly())->toBe(true);

        });

    });

    describe("->match()", function() {

        it("checks if the cookie match an URL", function() {

            $cookie = new SetCookie('bar', [
                'path'   => '/blog',
                'domain' => '.domain.com',
                'secure' => false
            ]);

            expect($cookie->match('http://www.domain.com/blog'))->toBe(true);
            expect($cookie->match('http://www.domain.com/blog/'))->toBe(true);
            expect($cookie->match('http://www.domain.com/news'))->toBe(false);

            expect($cookie->match('https://www.domain.com/blog'))->toBe(false);
            expect($cookie->match('http://www.domain2.com/blog'))->toBe(false);
        });

        it("checks cookies with no domain doesn't match", function() {

            $cookie = new SetCookie('bar', [
                'path'   => '/blog',
                'secure' => false
            ]);

            expect($cookie->match('http://www.domain.com/blog'))->toBe(false);
        });

        it("checks cookies with a trailing slashed Path doesn't match no trailing slash URL", function() {

            $cookie = new SetCookie('bar', [
                'path'   => '/blog/',
                'secure' => false
            ]);

            expect($cookie->match('http://www.domain.com/blog'))->toBe(false);
        });

    });

});
