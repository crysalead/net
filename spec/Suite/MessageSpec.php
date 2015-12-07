<?php
namespace Lead\Net\Spec\Suite;

use Exception;
use InvalidArgumentException;
use Lead\Net\Message;
use Lead\Net\Headers;

describe("Message", function() {

    describe("->headers()", function() {

        beforeEach(function() {
            $this->headers = <<<EOD
Custom-Header: Custom Value

EOD;
        });

        it("sets headers as a string", function() {

            $message = new Message(['headers' => $this->headers]);
            expect($message->headers()->data())->toBe([
                'Custom-Header: Custom Value'
            ]);

        });

        it("sets headers as an object", function() {

            $message = new Message(['headers' => Headers::parse($this->headers)]);
            expect($message->headers()->data())->toBe([
                'Custom-Header: Custom Value'
            ]);

        });

    });

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

    describe("->scheme()", function() {

        it("gets the scheme", function() {

            $message = new Message(['scheme' => 'https']);
            expect($message->scheme())->toBe('https');

        });

        it("sets the scheme", function() {

            $message = new Message();
            $message->scheme('https');

            expect($message->scheme())->toBe('https');

        });

    });

    describe("->host()", function() {

        it("gets the host", function() {

            $message = new Message(['host' => 'www.domain.com']);
            expect($message->host())->toBe('www.domain.com');

        });

        it("sets the host", function() {

            $message = new Message();
            $message->host('www.domain.com');

            expect($message->host())->toBe('www.domain.com');

        });

    });

    describe("->port()", function() {

        it("gets the port", function() {

            $message = new Message(['port' => 8888]);
            expect($message->port())->toBe(8888);

        });

        it("returns the default scheme port if port is `null`", function() {

            $message = new Message(['scheme' => 'https']);
            expect($message->port())->toBe(443);

        });

        it("returns `null` for unregistered scheme", function() {

            $message = new Message();
            expect($message->port())->toBe(null);

        });

        it("sets the port", function() {

            $message = new Message();
            $message->port(8888);

            expect($message->port())->toBe(8888);

        });

    });

    describe("->path()", function() {

        it("gets the path", function() {

            $message = new Message(['path' => 'home/index.php']);
            expect($message->path())->toBe('/home/index.php');

        });

        it("sets the path", function() {

            $message = new Message();
            $message->path('/home/index.php');

            expect($message->path())->toBe('/home/index.php');

        });

        it("auto adds a leading slash", function() {

            $message = new Message();
            $message->path('home/index.php');

            expect($message->path())->toBe('/home/index.php');

        });

    });

    describe("->username()", function() {

        it("gets the username", function() {

            $message = new Message(['username' => 'username']);
            expect($message->username())->toBe('username');

        });

        it("sets the username", function() {

            $message = new Message();
            $message->username('username');

            expect($message->username())->toBe('username');

        });

    });

    describe("->password()", function() {

        it("gets the password", function() {

            $message = new Message(['password' => 'password']);
            expect($message->password())->toBe('password');

        });

        it("sets the password", function() {

            $message = new Message();
            $message->password('password');

            expect($message->password())->toBe('password');

        });

    });

    describe("->url()", function() {

        it("gets the url", function() {

            $message = new Message([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 80,
                'username' => 'username',
                'password' => 'password',
                'path'     => 'index.php'
            ]);
            expect($message->url())->toBe('http://username:password@www.domain.com/index.php');

        });

        it("gets the url with a custom port", function() {

            $message = new Message([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 8080,
                'username' => 'username',
                'password' => 'password',
                'path'     => 'index.php'
            ]);
            expect($message->url())->toBe('http://username:password@www.domain.com:8080/index.php');

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

    describe("->export()", function() {

        it("delegates to `->to('array')`", function() {

            $message = new Message();

            expect($message)->toReceive('to')->with('array');

            $message->to('array');

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

            expect($message->to('array'))->toBe([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 80,
                'username' => 'username',
                'password' => 'password',
                'path'     => '/index.php',
                'url'      => 'http://username:password@www.domain.com/index.php',
                'headers'  => []
            ]);

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
                    'scheme'   => $message->scheme(),
                    'host'     => $message->host(),
                    'port'     => $message->port(),
                    'username' => $message->username(),
                    'password' => $message->password(),
                    'path'     => $message->path(),
                    'url'      => $message->url()
                ];
            });
            expect($result)->toBe([
                'scheme'   => 'http',
                'host'     => 'www.domain.com',
                'port'     => 80,
                'username' => 'username',
                'password' => 'password',
                'path'     => '/index.php',
                'url'      => 'http://username:password@www.domain.com/index.php'
            ]);

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

            $headers = [
                'HTTP/1.1 200 OK',
                'Date: Thu, 25 Dec 2014 00:00:00 GMT',
                'Content-Type: text/html; charset=UTF-8',
                'Vary: Accept-Encoding, Cookie, User-Agent',
            ];

            $message = new Message([
                'headers' => $headers,
                'body'   => 'Body Message'
            ]);

            expect((string) $message)->toBe(join("\n", $headers) . "\n\nBody Message");

        });

    });

});