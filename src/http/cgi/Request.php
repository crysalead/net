<?php
namespace net\http\cgi;

use set\Set;

/**
 * CGI `Request`
 */
class Request extends \net\http\Request
{
    /**
     * Holds the server globals & environment variables.
     *
     * @var array
     */
    protected static $_env = [];

    /**
     * The HTTP headers of the request
     *
     * @var instance
     */
    protected $_headers = null;

    /**
     * Current url of request.
     *
     * @var string
     */
    protected $_url = null;

    /**
     * Params for request.
     *
     * @var array
     */
    protected $_params = [];

    /**
     * Route parameters that should persist when generating URLs in this request context.
     *
     * @var array
     */
    protected $_persist = [];

    /**
     * Data found in the HTTP request body, most often populated by `$_POST` and `$_FILES`.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Key/value pairs found encoded in the request URL after '?', populated by `$_GET`.
     *
     * @var array
     */
    protected $_query = [];

    /**
     * Base path.
     *
     * @var string
     */
    protected $_base = null;

    /**
     * If POST, PUT or PATCH data is coming from an input stream (rather than `$_POST`),
     * this specified where to read it from.
     *
     * @var resource
     */
    protected $_stream = null;

    /**
     * Options used to detect features of the request, using `is()`. For example:
     *
     * Custom detectors can be added using `detect()`.
     *
     * @see net\http\cgi\Request::is()
     * @see net\http\cgi\Request::detect()
     * @var array
     */
    protected $_detectors = [
        'mobile'  => ['HTTP_USER_AGENT', null],
        'ajax'    => ['HTTP_X_REQUESTED_WITH', 'XMLHttpRequest'],
        'flash'   => ['HTTP_USER_AGENT', 'Shockwave Flash'],
        'ssl'     => 'HTTPS',
        'get'     => ['REQUEST_METHOD', 'GET'],
        'post'    => ['REQUEST_METHOD', 'POST'],
        'put'     => ['REQUEST_METHOD', 'PUT'],
        'patch'   => ['REQUEST_METHOD', 'PATCH'],
        'delete'  => ['REQUEST_METHOD', 'DELETE'],
        'head'    => ['REQUEST_METHOD', 'HEAD'],
        'options' => ['REQUEST_METHOD', 'OPTIONS']
    );

    /**
     * Contains an array of content-types, sorted by quality (the priority which the browser
     * requests each type).
     *
     * @var array
     */
    protected $_acceptContent = [];

    /**
     * Holds the value of the current locale, set through the `locale()` method.
     *
     * @var string
     */
    protected $_locale = null;

    /**
     * Constructor
     *
     * @param array $config The available configuration options are the following. Further
     *                      options are inherited from the parent classes.
     *                      - `'base'`    _string_  : (defaults to `null`).
     *                      - `'url'`     _string_  : (defaults to `null`).
     *                      - `'data'`    _array_   : Additional data to use when initializing
     *                                                the request. Defaults to `[]`.
     *                      - `'stream'`  _resource_: Stream to read from in order to get the message
     *                                                body when method is POST, PUT or PATCH and data is empty.
     *                                                When not provided `php://input` will be used for reading.
     *                      - `'env'`     _array_   : Only for populating headers
     */
    public function __construct($config = [])
    {
        $defaults = [
            'base'      => '',
            'url'       => '',
            'data'      => [],
            'stream'    => null,
            'query'     => [],
            'detectors' => [],
            'env'       => []
        ];
        $config += $defaults;
        parent::__construct($config);

        $mobile = [
            'iPhone', 'MIDP', 'AvantGo', 'BlackBerry', 'J2ME', 'Opera Mini', 'DoCoMo', 'NetFront',
            'Nokia', 'PalmOS', 'PalmSource', 'portalmmm', 'Plucker', 'ReqwirelessWeb', 'iPod',
            'SonyEricsson', 'Symbian', 'UP\.Browser', 'Windows CE', 'Xiino', 'Android'
        ];
        if (!empty($config['detectors']['mobile'][1])) {
            $mobile = array_merge($mobile, (array) $config['detectors']['mobile'][1]);
        }
        $this->_detectors['mobile'][1] = $mobile;

        $this->_data = (array) $config['data'];
        $this->_base = $config['base'] ? '/' . trim($config['base'], '/') : '';
        $this->_url = '/' . trim($config['url'], '/');

        if (isset($this->data['_method'])) {
            $this->_computed['REQUEST_METHOD'] = strtoupper($this->data['_method']);
            unset($this->data['_method']);
        }

        $env = $config['env'];

        if (isset($env['CONTENT_TYPE'])) {
            $this->_headers['content-type'] = $env['CONTENT_TYPE'];
        }

        if (isset($env['CONTENT_LENGTH'])) {
            $this->_headers['content-length'] = $env['CONTENT_LENGTH'],
        }

        foreach ($env as $name => $value) {
            if ($name[0] === 'H' && strpos($name, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($name, 5));
                $this->_headers[$name] = $value;
            }
        }
    }

    /**
     * Initializes request object by setting up mobile detectors, determining method and
     * populating the data property either by using i.e. form data or reading from STDIN in
     * case binary data is streamed. Will merge any files posted in forms with parsed data.
     *
     * Note that only beginning with PHP 5.6 STDIN can be opened/read and closed more than once.
     */
    protected function _init() {
        parent::_init();







        $hasBody = in_array($this->method, array('POST', 'PUT', 'PATCH'));

        if (!$this->body && $hasBody && $type !== 'html') {
            $this->_stream = $this->_stream ?: fopen('php://input', 'r');
            $this->body = stream_get_contents($this->_stream);
            fclose($this->_stream);
        }
        if (!$this->data && $this->body) {
            $this->data = $this->body(null, array('decode' => true, 'encode' => false));
        }
        $this->body = $this->data;
    }

    /**
     * Returns information about the type of content that the client is requesting.
     *
     * @see Media::negotiate()
     * @param boolean|string $type If not specified, returns the media type name that the client
     *        prefers, using content negotiation. If a media type name (string) is passed, returns
     *        `true` or `false`, indicating whether or not that type is accepted by the client at
     *        all. If `true`, returns the raw content types from the `Accept` header, parsed into
     *        an array and sorted by client preference.
     * @return mixed Returns a simple type name if the type is registered (i.e. `'json'`), or
     *         a fully-qualified content-type if not (i.e. `'image/jpeg'`), or a boolean or array,
     *         depending on the value of `$type`.
     */
    public function accepts($type = null) {
        if ($type === true) {
            return $this->_parseAccept();
        }
        if (!$type && isset($this->params['type'])) {
            return $this->params['type'];
        }
        $media = $this->_classes['media'];
        return $media::negotiate($this) ?: 'html';
    }

    /**
     * Parses the `HTTP_ACCEPT` information the requesting client sends, and converts
     * that data to an array for consumption by the rest of the framework.
     *
     * @return array All the types of content the client can accept.
     */
    protected function _parseAccept() {
        if ($this->_acceptContent) {
            return $this->_acceptContent;
        }
        $accept = $this->env('HTTP_ACCEPT');
        $accept = (preg_match('/[a-z,-]/i', $accept)) ? explode(',', $accept) : array('text/html');

        foreach (array_reverse($accept) as $i => $type) {
            unset($accept[$i]);
            list($type, $q) = (explode(';q=', $type, 2) + array($type, 1.0 + $i / 100));
            $accept[$type] = ($type === '*/*') ? 0.1 : floatval($q);
        }
        arsort($accept, SORT_NUMERIC);

        if (isset($accept['application/xhtml+xml']) && $accept['application/xhtml+xml'] >= 1) {
            unset($accept['application/xml']);
        }
        $media = $this->_classes['media'];

        if (isset($this->params['type']) && ($handler = $media::type($this->params['type']))) {
            if (isset($handler['content'])) {
                $type = (array) $handler['content'];
                $accept = array(current($type) => 1) + $accept;
            }
        }
        return $this->_acceptContent = array_keys($accept);
    }

    /**
     * This method allows easy extraction of any request data using a prefixed key syntax. By
     * passing keys in the form of `'prefix:key'`, it is possible to query different information of
     * various different types, including GET and POST data, and server environment variables. The
     * full list of prefixes is as follows:
     *
     * - `'data'`: Retrieves values from POST data.
     * - `'params'`: Retrieves query parameters returned from the routing system.
     * - `'query'`: Retrieves values from GET data.
     * - `'env'`: Retrieves values from the server or environment, such as `'env:https'`, or custom
     *   environment values, like `'env:base'`. See the `env()` method for more info.
     * - `'http'`: Retrieves header values (i.e. `'http:accept'`), or the HTTP request method (i.e.
     *   `'http:method'`).
     *
     * This method is used in several different places in the framework in order to provide the
     * ability to act conditionally on different aspects of the request. See `Media::type()` (the
     * section on content negotiation) and the routing system for more information.
     *
     *  _Note_: All keys should be _lower-cased_, even when getting HTTP headers.
     *
     * @see lithium\action\Request::env()
     * @see lithium\net\http\Media::type()
     * @see lithium\net\http\Router
     * @param string $key A prefixed key indicating what part of the request data the requested
     *        value should come from, and the name of the value to retrieve, in lower case.
     * @return string Returns the value of a GET, POST, routing or environment variable, or an
     *         HTTP header or method name.
     */
    public function get($key) {
        list($var, $key) = explode(':', $key);

        switch (true) {
            case in_array($var, array('params', 'data', 'query')):
                return isset($this->{$var}[$key]) ? $this->{$var}[$key] : null;
            case ($var === 'env'):
                return $this->env(strtoupper($key));
            case ($var === 'http' && $key === 'method'):
                return $this->env('REQUEST_METHOD');
            case ($var === 'http'):
                return $this->env('HTTP_' . strtoupper($key));
        }
    }

    /**
     * Provides a simple syntax for making assertions about the properties of a request.
     * By default, the `Request` object is configured with several different types of assertions,
     * which are individually known as _detectors_. Detectors are invoked by calling the `is()` and
     * passing the name of the detector flag, i.e. `$request->is('<name>')`, which returns `true` or
     * `false`, depending on whether or not the the properties (usually headers or data) contained
     * in the request match the detector. The default detectors include the following:
     *
     * - `'mobile'`: Uses a regular expression to match common mobile browser user agents.
     * - `'ajax'`: Checks to see if the `X-Requested-With` header is present, and matches the value
     *    `'XMLHttpRequest'`.
     * - `'flash'`: Checks to see if the user agent is `'Shockwave Flash'`.
     * - `'ssl'`: Verifies that the request is SSL-secured.
     * - `'get'` / `'post'` / `'put'` / `'delete'` / `'head'` / `'options'`: Checks that the HTTP
     *   request method matches the one specified.
     *
     * In addition to the above, this method also accepts media type names (see `Media::type()`) to
     * make assertions against the format of the request body (for POST or PUT requests), i.e.
     * `$request->is('json')`. This will return `true` if the client has made a POST request with
     * JSON data.
     *
     * For information about adding custom detectors or overriding the ones in the core, see the
     * `detect()` method.
     *
     * While these detectors are useful in controllers or other similar contexts, they're also
     * useful when performing _content negotiation_, which is the process of modifying the response
     * format to suit the client (see the `'conditions'` field of the `$options` parameter in
     * `Media::type()`).
     *
     * @see Request::detect()
     * @see Media::type()
     * @param string $flag The name of the flag to check, which should be the name of a valid
     *        detector (that is either built-in or defined with `detect()`).
     * @return boolean Returns `true` if the detector check succeeds (see the details for the
     *         built-in detectors above, or `detect()`), otherwise `false`.
     */
    public function is($flag) {
        $media = $this->_classes['media'];

        if (!isset($this->_detectors[$flag])) {
            if (!in_array($flag, $media::types())) {
                return false;
            }
            return $this->type() === $flag;
        }
        $detector = $this->_detectors[$flag];

        if (!is_array($detector) && is_callable($detector)) {
            return $detector($this);
        }
        if (!is_array($detector)) {
            return (boolean) $this->env($detector);
        }
        list($key, $check) = $detector + array('', '');

        if (is_array($check)) {
            $check = '/' . join('|', $check) . '/i';
        }
        if (Validator::isRegex($check)) {
            return (boolean) preg_match($check, $this->env($key));
        }
        return ($this->env($key) === $check);
    }

    /**
     * Sets/Gets the content type. If `'type'` is null, the method will attempt to determine the
     * type from the params, then from the environment setting
     *
     * @param string $type a full content type i.e. `'application/json'` or simple name `'json'`
     * @return string A simple content type name, i.e. `'html'`, `'xml'`, `'json'`, etc., depending
     *         on the content type of the request.
     */
    public function type($type = null) {
        if (!$type && !empty($this->params['type'])) {
            $type = $this->params['type'];
        }
        return parent::type($type);
    }

    /**
     * Creates a _detector_ used with `Request::is()`.  A detector is a boolean check that is
     * created to determine something about a request.
     *
     * A detector check can be either an exact string match or a regular expression match against a
     * header or environment variable. A detector check can also be a closure that accepts the
     * `Request` object instance as a parameter.
     *
     * For example, to detect whether a request is from an iPhone, you can do the following:
     * ``` embed:lithium\tests\cases\action\RequestTest::testDetect(11-12) ```
     *
     * @see lithium\action\Request::is()
     * @param string $flag The name of the detector check. Used in subsequent calls to `Request::is()`.
     * @param mixed $detector Detectors can be specified in four different ways:
     *        - The name of an HTTP header or environment variable. If a string, calling the detector
     *          will check that the header or environment variable exists and is set to a non-empty
     *          value.
     *        - A two-element array containing a header/environment variable name, and a value to match
     *          against. The second element of the array must be an exact match to the header or
     *          variable value.
     *        - A two-element array containing a header/environment variable name, and a regular
     *          expression that matches against the value, as in the example above.
     *        - A closure which accepts an instance of the `Request` object and returns a boolean
     *          value.
     * @return void
     */
    public function detect($flag, $detector = null) {
        if (is_array($flag)) {
            $this->_detectors = $flag + $this->_detectors;
        } else {
            $this->_detectors[$flag] = $detector;
        }
    }

    /**
     * Gets the referring URL of this request.
     *
     * @param string $default Default URL to use if HTTP_REFERER cannot be read from headers.
     * @param boolean $local If true, restrict referring URLs to local server.
     * @return string Referring URL.
     */
    public function referer($default = null, $local = false) {
        if ($ref = $this->env('HTTP_REFERER')) {
            if (!$local) {
                return $ref;
            }
            $url = parse_url($ref) + array('path' => '');
            if (empty($url['host']) || $url['host'] === $this->env('HTTP_HOST')) {
                $ref = $url['path'];
                if (!empty($url['query'])) {
                    $ref .= '?' . $url['query'];
                }
                if (!empty($url['fragment'])) {
                    $ref .= '#' . $url['fragment'];
                }
                return $ref;
            }
        }
        return ($default !== null) ? $default : '/';
    }

    /**
     * Overrides `lithium\net\http\Request::to()` to provide the correct options for generating
     * URLs. For information about this method, see the parent implementation.
     *
     * @see lithium\net\http\Request::to()
     * @param string $format The format to convert to.
     * @param array $options Override options.
     * @return mixed The return value type depends on `$format`.
     */
    public function to($format, array $options = array()) {
        $defaults = array(
            'path' => $this->env('base') . '/' . $this->url
        );
        return parent::to($format, $options + $defaults);
    }

    /**
     * Sets or returns the current locale string. For more information, see
     * "[Globalization](http://li3.me/docs/manual/07_globalization)" in the manual.
     *
     * @param string $locale An optional locale string like `'en'`, `'en_US'` or `'de_DE'`. If
     *        specified, will overwrite the existing locale.
     * @return Returns the currently set locale string.
     */
    public function locale($locale = null) {
        if ($locale) {
            $this->_locale = $locale;
        }
        if ($this->_locale) {
            return $this->_locale;
        }
        if (isset($this->params['locale'])) {
            return $this->params['locale'];
        }
    }

    /**
     * Returns an instance of the current GCI request with all globals.
     */
    public static function current($base = '') {
        $env = static::env();

        $protocol =$env['SERVER_PROTOCOL'];
        list($scheme, $version) = explode('/', $config['protocol']);

        $uri = $env['REQUEST_URI'];
        list($uri) = explode('?', $uri, 2);
        $regexp = '/^' . preg_quote($base, '/') . '/';
        $url = '/' . trim(preg_replace($regexp, '', $uri), '/') ?: '/';

        $data = isset($_POST) ? $_POST : [];
        $data = static::files() + $data;

        $config = [
            'version' => $version,
            'scheme'  => strtolower($scheme) . ($env['HTTPS'] ? 's' : ''),
            'host'    => $env['HTTP_HOST'],
            'base'    => $base,
            'url'     => $url,
            'query'   => isset($_GET) ? $_GET : [],
            'data'    => $data,
            'method'  => $env['REQUEST_METHOD'],
            'env'     => $env
        ];
        $request = new static($config);

        $this->type($env['CONTENT_TYPE']);

        return $request;
    }

    /**
     * Queries PHP's environment settings from a standardized abstraction of environment variables.
     *
     * @param  string $key The environment variable name.
     * @return string      The requested variables value.
     */
    public static function env($key = null) {
        static::_env();

        if ($key === null) {
            return static::_env;
        }

        $key = strtoupper($key);
        if (array_key_exists(static::_env, $key)) {
            return static::_env[$key];
        }
    }

    /**
     * Build PHP's environment settings, and store an abstraction for standardizing expected
     * environment values across varying platforms, as well as specify custom environment flags.
     *
     * Defines an artificial `'PLATFORM'` environment variable as either `'IIS'`, `'CGI'`
     * or `null` to allow checking for the SAPI in a normalized way.
     */
    protected static function _env() {
        if (static::_env !== null) {
            return;
        }

        $env = isset($_SERVER) ? $_SERVER : [];
        $env += isset($_ENV) ? $_ENV = [];

        $platforms = ['isapi' => 'IIS', 'cgi' => 'CGI', 'cgi-fcgi' => 'CGI'];

        $env += [
            'SERVER_NAME'          => 'localhost',
            'SERVER_PORT'          => 80,
            'HTTP_HOST'            => 'localhost',
            'HTTP_USER_AGENT'      => 'PHP',
            'HTTP_ACCEPT'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR'          => '127.0.0.1',
            'SCRIPT_NAME'          => '',
            'SCRIPT_FILENAME'      => '',
            'SERVER_PROTOCOL'      => 'HTTP/1.1',
            'REQUEST_TIME'         => time(),
            'CONTENT_TYPE'         => 'text/html',
            'PLATFORM'             => isset($platforms[PHP_SAPI]) ? $platforms[PHP_SAPI] : null,
            'PHP_SELF'             => '/'
        ];

        if (isset($env['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $env['REQUEST_METHOD'] = $env['HTTP_X_HTTP_METHOD_OVERRIDE'];
        } elseif (isset($_POST['_method'])) {
            $env['REQUEST_METHOD'] = strtoupper($_POST['_method']);
        } elseif (!isset($env['REQUEST_METHOD'])) {
            $env['REQUEST_METHOD'] = 'GET';
        }

        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_PC_REMOTE_ADDR', 'HTTP_X_REAL_IP'] as $key) {
            if (isset($env[$key])) {
                $addrs = explode(', ', $env[$key]);
                $env['REMOTE_ADDR'] = reset($addrs);
                break;
            }
        }

        $env['HTTP_BASE'] = preg_replace('/^([^.])*/i', null, $env['HTTP_HOST']);
        $env['SCRIPT_NAME'] = $env['PLATFORM'] === 'CGI' ? (isset($env['SCRIPT_URL']) ? $env['SCRIPT_URL'] : null) : null;

        if (isset($env['SCRIPT_URI'])) {
            $env['HTTPS'] = strpos($env['SCRIPT_URI'], 'https://') === 0
        } elseif (isset($env['HTTPS'])) {
            $env['HTTPS'] = (!empty($env['HTTPS']) && $env['HTTPS'] !== 'off');
        } else {
            $env['HTTPS'] = false;
        }

        if (empty($env['SERVER_ADDR']) && !empty($env['LOCAL_ADDR'])) {
            $env['SERVER_ADDR'] = $env['LOCAL_ADDR'];
        }

        if ($env['PLATFORM'] === 'IIS') {
            $env['SCRIPT_FILENAME'] = str_replace('\\\\', '\\', $env['PATH_TRANSLATED']);
            $env['DOCUMENT_ROOT'] = substr($env['SCRIPT_FILENAME'], 0, -strlen($env['SCRIPT_NAME']));
        } else {
            $env['DOCUMENT_ROOT'] = isset($env['DOCUMENT_ROOT']) ? $env['DOCUMENT_ROOT'] : '';
            $env['SCRIPT_FILENAME'] = $env['DOCUMENT_ROOT'] . $env['PHP_SELF'];
        }

        $header = isset($env['HTTP_AUTHORIZATION']) ? $env['HTTP_AUTHORIZATION'] : '';
        if (!$header && isset($env['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $env['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (stripos($header, 'basic') === 0) {
            $decoded = base64_decode(substr($header, strlen('basic ')));

            if (strpos($decoded, ':') !== false) {
                list($user, $password) = explode(':', $decoded, 2);
                $env['PHP_AUTH_USER'] = $user;
                $env['PHP_AUTH_PW'] = $password;
            }
        } elseif (stripos($header, 'digest') === 0) {
            $env['PHP_AUTH_DIGEST'] = substr($header, strlen('digest '));
        }

        static::_env = $env;
    }

    /**
     * Normalizes the data from `$_FILES`.
     *
     * @return array       Normalized data.
     */
    public static function files() {
        $data = isset($_FILES) ? $_FILES : [];
        $result = [];

        $normalize = function($key, $value) use ($result, &$normalize){
            foreach ($value as $param => $content) {
                foreach ($content as $num => $val) {
                    if (is_numeric($num)) {
                        $result[$key][$num][$param] = $val;
                        continue;
                    }
                    if (is_array($val)) {
                        foreach ($val as $next => $one) {
                            $result[$key][$num][$next][$param] = $one;
                        }
                        continue;
                    }
                    $result[$key][$num][$param] = $val;
                }
            }
            return $result;
        };
        foreach ($data as $key => $value) {
            if (isset($value['name'])) {
                if (is_string($value['name'])) {
                    $result[$key] = $value;
                    continue;
                }
                if (is_array($value['name'])) {
                    $result += $normalize($key, $value);
                }
            }
        }
        return $result;
    }
}
