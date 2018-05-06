<?php
namespace Lead\Net\Spec\Suite\Http\Cookie;

use Exception;
use Lead\Net\Http\Cookie\Cookie;

describe("Cookie", function() {

    describe("->__construct()", function() {

        it("sets default values", function() {

            $cookie = new Cookie();

            $this->expect($cookie->data())->toBe([
                'name'     => '',
                'value'    => '',
                'expires'  => null,
                'path'     => '/',
                'domain'   => null,
                'max-age'  => null,
                'secure'   => false,
                'httponly' => false
            ]);

        });

    });

    describe("->value()", function() {

        it("gets the value", function() {

            $cookie = new Cookie(['value' => 'foo']);
            $this->expect($cookie->value())->toBe('foo');

        });

        it("sets the value", function() {

            $cookie = new Cookie();
            $cookie->value('bar');

            $this->expect($cookie->value())->toBe('bar');
        });

        it("casts value as string", function() {

            $cookie = new Cookie();
            $cookie->value(0);

            $this->expect($cookie->value())->toBe('0');

        });

    });

    describe("->domain()", function() {

        it("gets the Domain", function() {

            $cookie = new Cookie(['domain' => '.foo.com']);

            $this->expect($cookie->domain())->toBe('.foo.com');

        });

        it("sets the Domain", function() {

            $cookie = new Cookie();
            $cookie->domain('.bar.com');

            $this->expect($cookie->domain())->toBe('.bar.com');

        });

    });

    describe("->path()", function() {

        it("gets the Path", function() {

            $cookie = new Cookie(['path' => '/home']);

            $this->expect($cookie->path())->toBe('/home');

        });

        it("sets the Path", function() {

            $cookie = new Cookie();
            $cookie->path('/blog');
            $this->expect($cookie->path())->toBe('/blog');

        });

        it("throws an exception with an invalid Path", function() {

            $closure = function() {
                $cookie = new Cookie(['path' => 'noleadingslash']);
            };
            expect($closure)->toThrow(new Exception("Invalid Path `'noleadingslash'`, non empty and leading slash are required."));

        });

    });

    describe("->expires()", function() {

        it("gets the default Expires", function() {

            $cookie = new Cookie();

            $this->expect($cookie->expires())->toBe(0);

        });

        it("gets the Expires", function() {

            $cookie = new Cookie(['expires' => strtotime('2014-12-25 00:00:00 UTC')]);

            $this->expect($cookie->expires())->toBe(strtotime('2014-12-25 00:00:00 UTC'));

        });

        it("sets the Expires with a timestamp", function() {

            $cookie = new Cookie();
            $cookie->expires(strtotime('2014-12-25 00:00:00 UTC'));

            $this->expect($cookie->expires())->toBe(strtotime('2014-12-25 00:00:00 UTC'));

        });

        it("sets the Expires with a string", function() {

            $cookie = new Cookie();
            $cookie->expires('2014-12-25 00:00:00 UTC');

            $this->expect($cookie->expires())->toBe(strtotime('2014-12-25 00:00:00 UTC'));

        });

    });

    describe("->maxAge()", function() {

        it("gets the Max-Age", function() {

            $cookie = new Cookie(['max-age' => 100]);

            $this->expect($cookie->maxAge())->toBe(100);

        });

        it("sets the Max-Age", function() {

            $cookie = new Cookie();
            $cookie->maxAge(100);
            $this->expect($cookie->maxAge())->toBe(100);

        });

    });

    describe("->secure()", function() {

        it("gets the Secure", function() {

            $cookie = new Cookie(['secure' => true]);

            $this->expect($cookie->secure())->toBe(true);

        });

        it("sets the Secure", function() {

            $cookie = new Cookie();
            $cookie->secure(true);

            $this->expect($cookie->secure())->toBe(true);

        });

    });

    describe("->httpOnly()", function() {

        it("gets the HttpOnly", function() {

            $cookie = new Cookie(['httponly' => true]);

            $this->expect($cookie->httpOnly())->toBe(true);

        });

        it("sets the HttpOnly", function() {

            $cookie = new Cookie('foo');
            $cookie->httpOnly(true);

            $this->expect($cookie->httpOnly())->toBe(true);

        });

    });

    describe("->matches()", function() {

        it("checks if the cookie match an URL", function() {

            $cookie = new Cookie([
                'name'  => 'foo',
                'value'  => 'bar',
                'path'   => '/blog',
                'domain' => '.domain.com',
                'secure' => false
            ]);

            expect($cookie->matches('http://www.domain.com/blog'))->toBe(true);
            expect($cookie->matches('http://www.domain.com/blog/'))->toBe(true);
            expect($cookie->matches('http://www.domain.com/news'))->toBe(false);

            expect($cookie->matches('https://www.domain.com/blog'))->toBe(false);
            expect($cookie->matches('http://www.domain2.com/blog'))->toBe(false);
        });

        it("checks if the cookie match some URLs", function() {

             foreach ([
                ['/foo', '/foo', true],
                ['/foo', '/Foo', false],
                ['/foo', '/fo', false],
                ['/foo', '/foo/bar', true],
                ['/foo', '/foo/bar/baz', true],
                ['/foo', '/foo/bar//baz', true],
                ['/foo', '/foobar', false],
                ['/foo/bar', '/foo', false],
                ['/foo/bar', '/foobar', false],
                ['/foo/bar', '/foo/bar', true],
                ['/foo/bar', '/foo/bar/', true],
                ['/foo/bar', '/foo/bar/baz', true],
                ['/foo/bar/', '/foo/bar', false],
                ['/foo/bar/', '/foo/bar/', true],
                ['/foo/bar/', '/foo/bar/baz', true]
            ] as $value) {

                $cookie = new Cookie([
                    'name'  => 'foo',
                    'value'  => 'bar',
                    'path' => $value[0],
                    'domain' => '.domain.com'
                ]);
                expect($cookie->matches('http://www.domain.com' . $value[1]))->toBe($value[2]);
            }

        });

        it("checks cookies with no domain doesn't match", function() {

            $cookie = new Cookie([
                'name'  => 'foo',
                'value'  => 'bar',
                'path'   => '/blog',
                'secure' => false
            ]);

            expect($cookie->matches('http://www.domain.com/blog'))->toBe(false);
        });

        it("checks cookies with a trailing slashed Path doesn't match no trailing slash URL", function() {

            $cookie = new Cookie([
                'name'  => 'foo',
                'value'  => 'bar',
                'path'   => '/blog/',
                'secure' => false
            ]);

            expect($cookie->matches('http://www.domain.com/blog'))->toBe(false);
        });

        it("checks cookies on a subdomain doesn't match any IP value", function() {

            $cookie = new Cookie([
                'name'  => 'foo',
                'value'  => 'bar',
                'path'   => '/blog',
                'domain' => '.167.14.18',
                'secure' => false
            ]);

            expect($cookie->matches('http://190.167.14.18/blog'))->toBe(false);
        });

        it("checks cookies on an IP match its corresponding IP", function() {

            $cookie = new Cookie([
                'name'  => 'foo',
                'value'  => 'bar',
                'path'   => '/blog',
                'domain' => '190.167.14.18',
                'secure' => false
            ]);

            expect($cookie->matches('http://190.167.14.18/blog'))->toBe(true);
        });

    });

    describe("->expired()", function() {

        it("checks if the Cookie expired", function() {

            $cookie = new Cookie(['name' => 'foo', 'expires' => strtotime('+1 day')]);
            $this->expect($cookie->expired())->toBe(false);

            $cookie = new Cookie(['name' => 'foo', 'expires' => strtotime('-1 day')]);
            $this->expect($cookie->expired())->toBe(true);

        });

        it("checks if the Cookie expires at the end of session", function() {

            $cookie = new Cookie('foo', ['expires' => 0]);
            $this->expect($cookie->expired())->toBe(false);
            $this->expect($cookie->expired(true))->toBe(true);

        });

    });

    describe("->toString()", function() {

        it("returns a string representation of a cookie", function() {

            $cookie = new Cookie([
                'name' => 'test',
                'value' => '123',
                'domain' => '.foo.com',
                'expires' => 1382916008,
                'path' => '/abc',
                'httponly' => true,
                'secure' => true
            ]);

            expect($cookie->toString())->toBe('test=123; Domain=.foo.com; Path=/abc; Expires=Sun, 27 Oct 2013 23:20:08 GMT; Secure; HttpOnly');

        });
    });

});
