# Net - Network Message Library

[![Build Status](https://travis-ci.org/crysalead/net.svg?branch=master)](https://travis-ci.org/crysalead/net)
[![Code Coverage](https://scrutinizer-ci.com/g/crysalead/net/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/crysalead/net/)

This library is a Network Message implementation in PHP. The library provides some PSR-7 compatible accessors and can be used with any PSR-7 compatible transport layer library.

## Installation

```bash
composer require crysalead/net
```

## API

### The HTTP Request class

```php
use Lead\Net\Http\Request;

$request = new Request([
    'method'   => 'POST',
    'scheme'   => 'http',
    'version'  => '1.1',
    'host'     => 'www.domain.com',
    'port'     => 8080,
    'username' => 'username',
    'password' => 'password',
    'path'     => '/index.php',
    'query'    => ['foo' => 'bar'],
    'fragment' => '#quz',
    'type'     => 'application/json',
    'data'     => '['foo' => 'bar']'
]);

// Getters
$request->method();   // POST
$request->scheme();   // http
$request->version();  // 1.1
$request->protocol(); // HTTP/1.1
$request->host();     // www.domain.com:8080
$request->hostname(); // www.domain.com
$request->port();     // 80
$request->username(); // username
$request->password(); // password
$request->query();    // ['foo' => 'bar']
$request->fragment(); // '#quz'
$request->url();      // http://www.domain.com:8080/index.php
$request->type();     // application/json
$request->encoding(); // <none>
$request->get();      // ['foo' => 'bar']
$request->body();     // '{"foo":"bar"}'
$request->stream();   // the plain body stream instance
$request->mode();     // origin
$request->line();     // POST /index.php HTTP/1.1
$request->data();     // exports the request as an array
$request->toString(); // exports the request as an string
(string) $request;    // exports the request as an string

// Setters
$request->method('PATCH');
$request->scheme('https');
$request->version('1.0');
$request->host('www.domain.com:8000');
$request->port('80');
$request->query(['foo' => 'baz']);
$request->fragment(#qaz);
$request->type('application/json');
$request->set(['foo' => 'baz']);
$request->body('{"foo":"baz"}');
$request->mode('absolute');

$request->username('username');
$request->password('password');

$request->auth(); // Generates a Basic auth header from credentials.

// Generates a Digest auth header from credentials
$request->auth([
    'realm' => 'app',
    'qop' => 'auth',
    'nonce' => '4bca0fbca7bd0',
    'opaque' => 'd3fb67a7aa4d887ec4bf83040a820a46'
]);

$request->auth(false); // Removes Authorization header from headers
```

Example of creating a request from an absolute URL:

```php
use Lead\Net\Http\Request;

$request = Request::parseUrl('http://username:password@www.domain.com:8080/index.php', [
    /* additionnal options */
]);

```

### The HTTP Response class

```php
use Lead\Net\Http\Response;

$response = new Response([
    'status'  => [200, 'OK'],
    'version' => '1.1',
    'type'    => 'text/html; charset=utf-8',
    'body'    => '<html></html>'
]);

// Getters
$response->status();   // [200, 'OK']
$response->version();  // 1.1
$response->protocol(); // HTTP/1.1
$response->type();     // text/html
$response->encoding(); // utf-8
$response->digest();   // Looks at the WWW-Authenticate headers and returns an array of key/values.
$response->get();      // '<html></html>'
$response->body();     // '<html></html>'
$response->stream();   // a Stream instance of the plain body
$response->line();     // HTTP/1.1 200 OK
$response->data();     // exports the response as an array
$response->toString(); // exports the response as an string
(string) $response;    // exports the response as an string

// Setters
$response->status(404);
$response->status([404, 'Not Found']);
$response->version('1.0');
$response->type('text/plain');
$response->set('Not Found');
$response->body('Not Found');
$response->cache(false);      // Disable cache
$response->cache('+2 weeks'); // 2 weeks cache
//
```

Example of creating a response from a plain string body:

```php
use Lead\Net\Http\Response;

$response = Response::parse(join("\r\n", [
    'HTTP/1.1 200 OK',
    'Connection: close',
    'Content-Type: text/plain;charset=UTF8',
    'Content-Length: 5',
    'Set-Cookie: doctor=who; Path=/tardis; HttpOnly',
    'Set-Cookie: test=foo%20bar; Expires=Fri, 25 Dec 2015 00:00:00 GMT; Secure',
    'Set-Cookie: test=foo%2Bbin; Path=/test; Domain=.domain.com',
    '',
    'Test!'
]));

```

### The HTTP Headers class

You can access HTTP Headers from both requests and responses instance through the `headers` public member.

```php
$request = new Request();
$request->headers['Content-Type'];
$request->headers['Vary'] = 'Accept-Encoding';
$request->headers['Vary'][] = 'Cookie';
$request->headers['Vary'][] = 'User-Agent';
$request->headers['Cookie'] = 'foo1=bar1; foo2=bar2; foo3=bar3';

// You can also access cookies through the `cookies` public member from headers.
$request->headers->cookies['foo4'] = 'bar4';
$request->headers->cookies['foo4'][] = 'bar44';

(string) $request->headers->cookies; // Cookie header representation
(string) $request->headers;          // Headers representation
```

Headers work the same way for responses:

```php
$response = new Responses();
$response->headers['Cache-Control'] = 'no-store';
$response->headers['Cache-Control'][] = 'no-cache';
$response->headers['Cache-Control'][] = 'must-revalidatee';
$response->headers['Cache-Control'][] = 'max-age';
// You can also use `$response->cache('+2 weeks')` or `$response->cache(false)` to  control caches;

// You can set several Set-Cookie with the same name as long as the path and or domain differ.
$request->headers->cookies['foo'] = ['value' => 'quz', 'path' => '/foo'];
$request->headers->cookies['foo'] = ['value' => 'qaz', 'path' => '/foo/bar'];

```

Note: cookies in responses are in `Set-Cookie` headers.

### The CGI Request class

```php
use Lead\Net\Http\Cgi\Request;

// Creates a server request build with CGI global vars (ie. $_SERVER, $_POST, $_GET, $_COOKIE, $_FILES)
$request = Request::ingoing();
```

### Acknowledgements

- [Li3](https://github.com/UnionOfRAD/lithium)
- [zend-diactoros](https://github.com/zendframework/zend-diactoros)
- [Requests](https://github.com/rmccue/Requests)
- [Guzzle](https://github.com/guzzle/guzzle)
