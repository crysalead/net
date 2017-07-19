<?php
require __DIR__ . '/../../vendor/autoload.php';

use Lead\Net\Http\Cgi\Request;
use Lead\Net\Http\Response;
use Lead\Net\Http\Auth;

class Router
{
    protected $_routes = [];

    public function __call($method, $params)
    {
        $this->_routes[strtoupper($method)][$params[0]] = [
            'regex' => $this->regexp($params[0]), 'closure' => $params[1]
        ];
        return $this;
    }

    public function regexp($path)
    {
        $path = preg_replace_callback("~\{:([a-zA-Z][a-zA-Z0-9_]*)(?::([^{}]*(?:\{(?-1)\}[^{}]*)*))?\}~i", function($matches) {
            return '(' . (isset($matches[2]) ? $matches[2] : '[^/]+') . ')';
        }, $path);
        return '~^' . $path . '/?$~';
    }

    public function routes($method)
    {
        return isset($this->_routes[$method]) ? $this->_routes[$method] : [];
    }
}

class Dispatcher
{
    protected $_request = null;

    public function dispatch($request, $router)
    {
        $this->_request = $request;

        $method = $request->method();
        $path = $request->path();

        $routes = $router->routes($method);
        krsort($routes);

        foreach ($routes as $route) {
            $regex = $route['regex'];
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                $closure = $route['closure']->bindTo($this);
                $response = call_user_func_array($closure, $matches);
                if ($response) {
                    header('Content-Length: ' . strlen($response));
                    echo $response;
                }
                return;
            }
        }
        http_response_code(404);
    }

    public function request()
    {
        return $this->_request;
    }
}

function requestToArray($request, $allowed = null)
{
    $data = array_filter($request->export());
    $data['headers'] = $request->headers()->to('array');
    if ($allowed) {
        $data = array_intersect_key($data, array_fill_keys((array) $allowed, true));
    }

    $data['args'] = $request->query();
    if ($request->body()) {
        $data['body'] = $request->body();
    }

    return $data;
}

function requestToJson($request, $allowed = null)
{
    return arrayToJson(requestToArray($request, $allowed));
}


function arrayToJson($data)
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
}

$dispatcher = new Dispatcher();

$on = new Router();

$on->get('/ip', function() {
    header('Content-Type: application/json');
    $origin = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
    return arrayToJson(['origin' => $origin]);
});

$on->get('/user-agent', function() {
    header('Content-Type: application/json');
    return arrayToJson(['user-agent' => $_SERVER['HTTP_USER_AGENT']]);
});

$on->get('/headers', function() {
    header('Content-Type: application/json');
    return requestToJson($this->request(), ['headers']);
});

$on->get('/get', function() {
    header('Content-Type: application/json');
    return requestToJson($this->request());
});

$on->head('/head', function() {
    header('Content-Type: application/json');
    return requestToJson($this->request());
});

$on->post('/post', function() {
    header('Content-Type: application/json');
    return requestToJson($this->request());
});

$on->patch('/patch', function() {
    header('Content-Type: application/json');
    return requestToJson($this->request());
});

$on->put('/put', function() {
    header('Content-Type: application/json');
    return requestToJson($this->request());
});

$on->delete('/delete', function() {
    header('Content-Type: application/json');
    return requestToJson($this->request());
});

$on->get('/encoding/utf8', function() {
    header('Content-Type: text/html; charset=utf-8');
    return "STARGΛ̊TE SG-1, ᚻᛖ ᚳᚹᚫᚦ ᚦᚫᛏ ᚻᛖ ᛒᚢᛞᛖ ᚩᚾ ᚦᚫᛗ ᛚᚪᚾᛞᛖ ᚾᚩᚱᚦᚹᛖᚪᚱᛞᚢᛗ ᚹᛁᚦ ᚦᚪ ᚹᛖᛥᚫ";
});

$on->get('/gzip', function() {
    header('Content-Encoding: gzip');
    return gzencode(arrayToJson(['gzipped' => true] + requestToArray($this->request())));
});

$on->get('/deflate', function() {
    header('Content-Encoding: deflate');
    return gzdeflate(arrayToJson(['deflated' => true] + requestToArray($this->request())));
});

$on->get('/status/{:code}', function($code) {
    http_response_code($code);
});

$on->get('/response-headers', function($matches) {
    header('Content-Type: application/json');
    foreach ($_GET as $key => $value) {
        header($key . ': ' . $value);
    }
    return arrayToJson($_GET);
});

$on->get('/(?:relative-)?redirect/{:n:[^/]*}', function($n) {
    $n = max(1, (integer) $n) - 1;
    if ($n === 0) {
        header('Location: /get');
    } else {
        header("Location: /relative-redirect/{$n}");
    }
});

$on->get('/absolute-redirect/{:n:[^/]*}', function($n) {
    $n = max(1, (integer) $n) - 1;
    if ($n === 0) {
        header('Location: /get');
    } else {
        $host = $this->request()->host();
        header("Location: {$host}/absolute-redirect/{$n}");
    }
});

$on->get('/redirect-to', function() {
    $location = isset($_GET['url']) ? $_GET['url'] : '/';
    header("Location: {$location}");
});

$on->get('/cookies', function() {
    header('Content-Type: application/json');
    return arrayToJson(['cookies' => $_COOKIE]);
});

$on->get('/cookies/set', function() {
    foreach ($_GET as $k => $v) {
        setcookie($k, $v);
    }
    header('Location: /cookies');
});

$on->get('/cookies/delete', function() {
    foreach ($_GET as $k => $v) {
        setcookie($k, '', 1);
    }
    header('Location: /cookies');
});

$on->get('/basic-auth/{:user}/{:passwd}', function($user, $passwd) {

    $basic = Auth::encode($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    $encoded = Auth::encode($user, $passwd);

    if ($basic['response'] === $encoded['response']) {
        header('Content-Type: application/json');
        return arrayToJson(['authenticated' => true]);
    }

    header("WWW-Authenticate: Basic realm=\"realm\"");
    http_response_code(401);
});

$on->get('/hidden-basic-auth/{:user}/{:passwd}', function($user, $passwd) {

    $basic = Auth::encode($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    $encoded = Auth::encode($user, $passwd);

    if ($basic['response'] === $encoded['response']) {
        header('Content-Type: application/json');
        return arrayToJson(['authenticated' => true]);
    }

    http_response_code(404);
});

$on->get('/digest-auth/{:realm}/{:qop}/{:user}/{:passwd}', function($realm, $qop, $user, $passwd) {

    $data = Auth::decode($_SERVER['PHP_AUTH_DIGEST']);

    $data['realm'] = $realm;
    $data['qop'] = $qop;
    $data['method'] = $this->request()->method();

    $encoded = Auth::encode($user, $passwd, $data);

    if ($data['response'] === $encoded['response']) {
        header('Content-Type: application/json');
        return arrayToJson(['authenticated' => true]);
    }

    header("WWW-Authenticate: Basic realm=\"{$realm}\"");
    http_response_code(401);
});

$on->get('/digest-auth/{:realm}/{:qop}/{:user}/{:passwd}', function($realm, $qop, $user, $passwd) {

    $data = Auth::decode($_SERVER['PHP_AUTH_DIGEST']);

    $data['realm'] = $realm;
    $data['qop'] = $qop;
    $data['method'] = $this->request()->method();

    $encoded = Auth::encode($user, $passwd, $data);

    if ($data['response'] === $encoded['response']) {
        header('Content-Type: application/json');
        return arrayToJson(['authenticated' => true]);
    }

    http_response_code(404);
});

$on->get('/stream/{:n}', function($n) {
    $n = max(1, (integer) $n);
    $request = $this->request();

    $response = new Response([
        'headers' => [
            'Transfer-Encoding: chunked'
        ]
    ]);

    $response->dump();
    for ($i = 0; $i < $n; $i++) {
        $response->push("Line number {$i}\n");
    }
    $response->end();
});

$on->get('/delay/{:n}', function($n) {
    header('Content-Type: application/json');
    sleep(min(10, (integer) $n));
    return requestToJson($this->request());
});

$on->get('/drip', function() {
    $defaults = [
        'numbytes' => 10,
        'duration' => 2,
        'delay'    => 0,
        'code'     => 200
    ];
    $params = $_GET + $defaults;
    if ($params['delay']) {
        sleep(min(10, (integer) $n));
    }
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $params['numbytes']);
    http_response_code($params['code']);

    $pause = $params['duration'] / $params['numbytes'];

    for ($i = 0; $i < $params['numbytes']; $i++) {
        usleep($pause * 1000000);
        echo '*';
    }
});

$on->get('/html', function() {
    header('Content-Type: text/html');
    return '<!DOCTYPE html><html><head></head><body><h1>Heading</h1><div><p>Hello World!</p></div></body></html>';
});

$on->get('/robots.txt', function() {
    header('Content-Type: text/plain');
    return 'User-agent: *' . "\n" . 'Disallow: /deny';
});

$on->get('/deny', function() {
    header('Content-Type: text/plain');
    return 'There\'s nothing here, move along...';
});

$on->get('/cache', function() {
    $headers = $this->request()->headers();
    if (!isset($headers['If-Modified-Since']) && !isset($headers['If-None-Match'])) {
        header('Content-Type: application/json');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s T'));
        header('ETag: ' . md5(time()));
        return requestToJson($this->request());
    }
    http_response_code(304);
});

$on->get('/cache/{:n}', function($n) {
    header("Cache-Control: public, max-age={$n}");
});

$on->get('/bytes/{:n}', function($n) {
    $n = min($n, 100 * 1024); // 100KB limit;
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $n);
    for ($i = 0; $i < $n; $i++) {
        echo chr(mt_rand(0, 255));
    }
});

$on->get('/stream-bytes/{:n}', function($n) {
    $n = min($n, 100 * 1024); // 100KB limit;
    header('Content-Type: application/octet-stream');
    header('Transfer-Encoding: chunked');
    $chunksize = isset($_GET['chunk_size']) ? $_GET['chunk_size'] : $n;
    $chuck = '';
    $cpt = 0;
    for ($i = 0; $i < $n; $i++) {
        $chuck .= chr(mt_rand(0, 255));
        $cpt++;
        if ($cpt >= $chunksize) {
            echo dechex($chunksize) . "\r\n";
            echo $chuck . "\r\n";
            $chuck = '';
            $cpt = 0;
        }
    }
    if ($chuck) {
        echo dechex(strlen($chuck)) . "\r\n";
        echo $chuck. "\r\n";
    }
    echo "0\r\n\r\n";
});

$on->get('/links/{:n}/?{:offset}?', function($n, $offset = 0) {
    $n = min(max(1, $n), 200);
    $offset = (integer) $offset;
    $links = '';
    for ($i = 0; $i < $n; $i++) {
        if ($i === $offset) {
            $links .= "{$i} ";
        } else {
            $links .= "<a href='/links/{$n}/{$i}'>{$i}</a> ";
        }
    }

    header('Content-Type: text/html');
    return "<html><head><title>Links</title></head><body>{$links}</body></html>";
});

$on->get('/xml', function() {
    header('Content-Type: application/xml');
    return '<?xml version="1.0" encoding="us-ascii"?><slide type="all"><title>Wake up to WonderWidgets!</title></slide>';
});

$on->get('/', function() {
    return <<<EOD
<!DOCTYPE html>
<html>
<head>
<meta http-equiv='content-type' value='text/html;charset=utf8'>
<title>httpecho(1): HTTP Client Testing Service</title>
<style type='text/css' media='all'>
    /* style: man */
    body#manpage {margin:0}
    .mp {text-align:justify;background:#fff}
    .mp ul {list-style: none; margin-left: 1em!important}
    .mp h1 {font-size:20px;line-height:2}
    .mp h1,.mp h2,.mp h3,.mp h4 {color:#030201}
    .mp p,.mp pre,.mp ul {margin:0 0 20px 8ex}
    .mp em {font-style:italic;color:#232221;text-decoration:none}
    .mp {padding:0 9ex 1ex 4ex}
    .mp h2 {font-size:16px;line-height:1.25;margin:10px 0 0 0}
    .mp h3 {margin:0 0 0 4ex}
    .mp pre {font-weight:normal;color:#434241; padding:0 4ex; margin-bottom:20px}
    .mp strong,.mp b {font-weight:bold}
    .mp img {display:block;margin:auto}
    .mp,.mp code,.mp pre,.mp h3,.mp h4 {font-family:monospace;font-size:14px;line-height:1.5}
    .mp a,.mp a:link,.mp a:hover {color:#0000ff}
</style>
</head>

<body id='manpage'>
<a href="http://github.com/crysalead/httpecho"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://s3.amazonaws.com/github/ribbons/forkme_right_darkblue_121621.png" alt="Fork me on GitHub"></a>

<div class='mp'>
<h1>httpecho(1): HTTP Request &amp; Response Service</h1>

<h2>ENDPOINTS</h2>

<ul>
<li><a href="/" data-bare-link="true"><strong>/</strong></a> This page.</li>
<li><a href="/ip" data-bare-link="true"><strong>/ip</strong></a> Returns Origin IP.</li>
<li><a href="/user-agent" data-bare-link="true"><strong>/user-agent</strong></a> Returns user-agent.</li>
<li><a href="/headers" data-bare-link="true"><strong>/headers</strong></a> Returns header dict.</li>
<li><a href="/get" data-bare-link="true"><strong>/get</strong></a> Returns GET data.</li>
<li><strong>/head</strong> Returns HEAD data.</li>
<li><strong>/post</strong> Returns POST data.</li>
<li><strong>/patch</strong> Returns PATCH data.</li>
<li><strong>/put</strong> Returns PUT data.</li>
<li><strong>/delete</strong> Returns DELETE data</li>
<li><a href="/encoding/utf8"><strong>/encoding/utf8</strong></a> Returns page containing UTF-8 data.</li>
<li><a href="/gzip" data-bare-link="true"><strong>/gzip</strong></a> Returns gzip-encoded data.</li>
<li><a href="/deflate" data-bare-link="true"><strong>/deflate</strong></a> Returns deflate-encoded data.</li>
<li><a href="/status/418"><strong>/status/:code</strong></a> Returns given HTTP Status code.</li>
<li><a href="/response-headers?Content-Type=text/plain;%20charset=UTF-8&amp;Server=httpecho"><strong>/response-headers?key=val</strong></a> Returns given response headers.</li>
<li><a href="/redirect/6"><strong>/redirect/:n</strong></a> 302 Redirects <em>n</em> times.</li>
<li><a href="/redirect-to?url=http://example.com/"><strong>/redirect-to?url=foo</strong></a> 302 Redirects to the <em>foo</em> URL.</li>
<li><a href="/relative-redirect/6"><strong>/relative-redirect/:n</strong></a> 302 Relative redirects <em>n</em> times.</li>
<li><a href="/absolute-redirect/6"><strong>/absolute-redirect/:n</strong></a> 302 Absolute redirects <em>n</em> times.</li>
<li><a href="/cookies" data-bare-link="true"><strong>/cookies</strong></a> Returns cookie data.</li>
<li><a href="/cookies/set?k1=v1&amp;k2=v2"><strong>/cookies/set?name=value</strong></a> Sets one or more simple cookies.</li>
<li><a href="/cookies/delete?k1&amp;k2"><strong>/cookies/delete?name</strong></a> Deletes one or more simple cookies.</li>
<li><a href="/basic-auth/user/passwd"><strong>/basic-auth/:user/:passwd</strong></a> Challenges HTTPBasic Auth.</li>
<li><a href="/hidden-basic-auth/user/passwd"><strong>/hidden-basic-auth/:user/:passwd</strong></a> 404'd BasicAuth.</li>
<li><a href="/digest-auth/realm/qop/user/passwd"><strong>/basic-auth/:realm/:qop/:user/:passwd</strong></a> Challenges HTTPDigest Auth.</li>
<li><a href="/hidden-digest-auth/realm/qop/user/passwd"><strong>/basic-auth/:realm/:qop/:user/:passwd</strong></a> 404'd DigestAuth.</li>
<li><a href="/stream/20"><strong>/stream/:n</strong></a> Streams <em>n</em>–100 lines.</li>
<li><a href="/delay/3"><strong>/delay/:n</strong></a> Delays responding for <em>n</em>–10 seconds.</li>
<li><a href="/drip?numbytes=5&amp;duration=5&amp;code=200"><strong>/drip?numbytes=n&amp;duration=s&amp;delay=s&amp;code=code</strong></a> Drips data over a duration after an optional initial delay, then (optionally) returns with the given status code.</li>
<li><a href="/html" data-bare-link="true"><strong>/html</strong></a> Renders an HTML Page.</li>
<li><a href="/robots.txt" data-bare-link="true"><strong>/robots.txt</strong></a> Returns some robots.txt rules.</li>
<li><a href="/deny" data-bare-link="true"><strong>/deny</strong></a> Denied by robots.txt file.</li>
<li><a href="/cache" data-bare-link="true"><strong>/cache</strong></a> Returns 200 unless an If-Modified-Since or If-None-Match header is provided, when it returns a 304.</li>
<li><a href="/cache/60"><strong>/cache/:n</strong></a> Sets a Cache-Control header for <em>n</em> seconds.</li>
<li><a href="/bytes/1024"><strong>/bytes/:n</strong></a> Generates <em>n</em> random bytes of binary data, accepts optional <em>seed</em> integer parameter.</li>
<li><a href="/stream-bytes/1024"><strong>/stream-bytes/:n</strong></a> Streams <em>n</em> random bytes of binary data, accepts optional <em>seed</em> and <em>chunk_size</em> integer parameters.</li>
<li><a href="/links/10"><strong>/links/:n</strong></a> Returns page containing <em>n</em> HTML links.</li>
<li><a href="/forms/post" data-bare-link="true"><strong>/forms/post</strong></a> HTML form that submits to <em>/post</em></li>
<li><a href="/xml" data-bare-link="true"><strong>/xml</strong></a> Returns some XML</li>
</ul>


<h2>DESCRIPTION</h2>

<p>All endpoint responses are JSON-encoded.</p>

<h2 id="EXAMPLES">EXAMPLES</h2>

<h3>$ curl http://localhost/ip</h3>

<pre><code>{"origin": "24.127.96.129"}
</code></pre>

<h3>$ curl http://localhost/user-agent</h3>

<pre><code>{"user-agent": "curl/7.19.7 (universal-apple-darwin10.0) libcurl/7.19.7 OpenSSL/0.9.8l zlib/1.2.3"}
</code></pre>

<h3>$ curl http://localhost/get</h3>

<pre><code>{
   "args": {},
   "headers": {
      "Accept": "*/*",
      "Connection": "close",
      "Content-Length": "",
      "Content-Type": "",
      "Host": "localhost",
      "User-Agent": "curl/7.19.7 (universal-apple-darwin10.0) libcurl/7.19.7 OpenSSL/0.9.8l zlib/1.2.3"
   },
   "origin": "24.127.96.129",
   "url": "http://localhost/get"
}
</code></pre>

<h3>$ curl -I http://localhost/status/418</h3>

<pre><code>HTTP/1.1 418 I'M A TEAPOT
Server: nginx/0.7.67
Date: Mon, 13 Jun 2011 04:25:38 GMT
Connection: close
x-more-info: http://tools.ietf.org/html/rfc2324
Content-Length: 135
</code></pre>

<h3>$ curl https://localhost/get?show_env=1</h3>

<pre><code>{
  "headers": {
    "Content-Length": "",
    "Accept-Language": "en-US,en;q=0.8",
    "Accept-Encoding": "gzip,deflate,sdch",
    "X-Forwarded-Port": "443",
    "X-Forwarded-For": "109.60.101.240",
    "Host": "localhost",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "User-Agent": "Mozilla/5.0 (X11; Linux i686) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.83 Safari/535.11",
    "X-Request-Start": "1350053933441",
    "Accept-Charset": "ISO-8859-1,utf-8;q=0.7,*;q=0.3",
    "Connection": "keep-alive",
    "X-Forwarded-Proto": "https",
    "Cookie": "_gauges_unique_day=1; _gauges_unique_month=1; _gauges_unique_year=1; _gauges_unique=1; _gauges_unique_hour=1",
    "Content-Type": ""
  },
  "args": {
    "show_env": "1"
  },
  "origin": "109.60.101.240",
  "url": "http://localhost/get?show_env=1"
}
</code></pre>

<h2>Running it</h2>

<pre><code class="bash">
$ php -S localhost:8080 httpecho.php
</code></pre>

<h2 id="AUTHOR">ACKNOWLEDGEMENTS</h2>

<p><a href="http://httpbin.org/">httpbin</a> - httpbin.</p>
</div>
</body>
</html>
EOD;
});

$dispatcher->dispatch(Request::ingoing(), $on);
