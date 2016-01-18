<?php
namespace Lead\Net\Http\Cgi;

use Lead\Net\NetException;
use Lead\Set\Set;

class Request extends \Lead\Net\Http\Request
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array' => 'Lead\Net\Http\Cgi\Request::toArray'
    ];

    /**
     * Holds the environment variable container.
     *
     * @var object
     */
    public $env = null;

    /**
     * Params for request.
     *
     * @var array
     */
    protected $_params = [];

    /**
     * Data found in the HTTP request body.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Temporaty base path.
     *
     * @var string
     */
    protected $_basePathTmp = null;

    /**
     * Base path.
     *
     * @var string
     */
    protected $_basePath = null;

    /**
     * Ignore pattern for the base path.
     *
     * @var string|array
     */
    protected $_ignorePath = null;

    /**
     * Holds the value of the current locale, set through the `locale()` method.
     *
     * @var string
     */
    protected $_locale = null;

    /**
     * Options used to detect features of the request, using `is()`.
     * Custom detectors can be added using `detect()`.
     *
     * @var array
     */
    protected $_detectors = [];

    /**
     * Constructor
     *
     * @param array $config The available configuration options are the following. Further
     *                      options are inherited from the parent classes.
     *                      - `'basePath'` _string_  : (defaults to `null`).
     *                      - `'url'`      _string_  : (defaults to `null`).
     *                      - `'data'`     _array_   : Additional data to use when initializing
     *                                                 the request. Defaults to `[]`.
     *                      - `'stream'`   _resource_: Stream to read from in order to get the message
     *                                                 body when method is POST, PUT or PATCH and data is empty.
     *                                                 When not provided `php://input` will be used for reading.
     *                      - `'env'`      _array_   : Only for populating headers
     */
    public function __construct($config = [])
    {
        $defaults = [
            'locale'    => null,
            'data'      => [],
            'params'    => [],
            'env'       => [],
            'classes'   => [
                'environment' => 'Lead\Env\Env',
                'auth'        => 'Lead\Net\Http\Auth'
            ],
            'detectors' => [
                'mobile'  => ['http:user-agent'       => [
                    'iPhone', 'MIDP', 'AvantGo', 'BlackBerry', 'J2ME', 'Opera Mini', 'DoCoMo', 'NetFront',
                    'Nokia', 'PalmOS', 'PalmSource', 'portalmmm', 'Plucker', 'ReqwirelessWeb', 'iPod',
                    'SonyEricsson', 'Symbian', 'UP\.Browser', 'Windows CE', 'Xiino', 'Android'
                ]],
                'ajax'    => ['http:x-requested-with' => 'XMLHttpRequest'],
                'flash'   => ['http:user-agent'       => 'Shockwave Flash'],
                'ssl'     => ['http:ssl'              =>  true],
                'get'     => ['http:method'           => 'GET'],
                'post'    => ['http:method'           => 'POST'],
                'put'     => ['http:method'           => 'PUT'],
                'patch'   => ['http:method'           => 'PATCH'],
                'delete'  => ['http:method'           => 'DELETE'],
                'head'    => ['http:method'           => 'HEAD'],
                'options' => ['http:method'           => 'OPTIONS']
            ]
        ];
        $config = Set::merge($defaults, $config);

        if (!is_object($config['env'])) {
            $environment = $config['classes']['environment'];
            $config['env'] = new $environment($config['env']);
        }

        $config += $this->_defaults($config);

        $this->env = $config['env'];
        $this->_data = $config['data'];
        $this->_params = $config['params'];

        unset($config['data']);
        parent::__construct($config);

        $this->_detectors = $config['detectors'];

        $this->ignorePath($config['ignorePath']);
        $this->basePath($config['basePath']);
        $this->locale($config['locale']);

        if (isset($this->_data['_method'])) {
            $this->env['REQUEST_METHOD'] = strtoupper($this->_data['_method']);
            $this->method($this->env['REQUEST_METHOD']);
            unset($this->_data['_method']);
        }
    }

    /**
     * Extracts all informations from environment variables and creates a consistent default config array.
     *
     * @param array $config Some config array.
     * @param array         The auto generated defaults
     */
    public function _defaults($config)
    {
        if (!isset($config['body'])) {
            $config['body'] = fopen('php://input', 'r');
        }
        $env = $config['env'];

        $env->set($env->data() + [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'CONTENT_TYPE'    => 'text/html; charset=utf-8',
            'HTTP_HOST'       => 'localhost',
            'SERVER_PORT'     => 80,
            'SCRIPT_NAME'     => '/',
            'REQUEST_URI'     => '/',
            'REQUEST_METHOD'  => 'GET'
        ]);

        list($scheme, $version) = explode('/', $env['SERVER_PROTOCOL']);

        $headers = [
            'Content-Type' => $env['CONTENT_TYPE']
        ];

        if (isset($env['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $env['CONTENT_LENGTH'];
        }

        foreach ($env as $name => $value) {
            if ($name[0] === 'H' && strpos($name, 'HTTP_') === 0) {
                $name = str_replace('_', ' ', substr($name, 5));
                $name = str_replace(' ', '-', ucwords(strtolower($name)));
                $headers[$name] = $value;
            }
        }

        $username = null;
        $password = null;

        $authorization = $env['HTTP_AUTHORIZATION'];

        if (stripos($authorization, 'basic') === 0) {
            $decoded = base64_decode(substr($authorization, strlen('basic ')));
            if (strpos($decoded, ':') !== false) {
                list($username, $password) = explode(':', $decoded, 2);
            }
        } elseif (stripos($authorization, 'digest') === 0) {
            $auth = $config['classes']['auth'];
            $data = $auth::decode(substr($authorization, strlen('digest ')));
            $username = !empty($data['username']) ? $data['username'] : null;
        }

        $defaults = [
            'version'    => $version,
            'scheme'     => strtolower($scheme) . ($env['HTTPS'] ? 's' : ''),
            'host'       => $env['HTTP_HOST'],
            'username'   => $username,
            'password'   => $password,
            'auth'       => null,
            'ignorePath' => null,
            'method'     => $env['REQUEST_METHOD'],
            'headers'    => $headers,
            'env'        => $env
        ];

        $uri = $env['REQUEST_URI'];
        list($path) = explode('?', $uri, 2);

        $basePath = '/' . ltrim($env['SCRIPT_NAME'], '/');

        if (strncmp($basePath, $path, strlen($basePath)) === 0){
            $path = '/' . (trim(substr($path, strlen($basePath)), '/') ?: '/');
            $basePath = '/' . ltrim(dirname($env['SCRIPT_NAME']), '/');
        } else {
            $i = 0;
            $len = min(strlen($path), strlen($basePath));
            while ($i < $len && $path[$i] === $basePath[$i]) {
                $i++;
            }
            $basePath = substr($path, 0, $i);
            $path = '/' . (trim(substr($path, $i), '/') ?: '/');
        }

        return $defaults += [
            'path'     => $path,
            'basePath' => $basePath
        ];
    }

    /**
     * Gets/sets the request params.
     *
     * @param  array $params The params to set or none to get the setted one.
     * @return array
     */
    public function params($params = null)
    {
        if (!func_num_args()) {
            return $this->_params;
        }
        $this->_params = $params;
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
     *
     * @param  string $key A prefixed key indicating what part of the request data the requested
     *                     value should come from, and the name of the value to retrieve, in lower case.
     * @return string      Returns the value of a GET, POST, routing environment, or HTTP header variable.
     */
    public function attr($key)
    {
        list($var, $key) = explode(':', $key);

        switch (true) {
            case in_array($var, array('params', 'data', 'query')):
                return isset($this->{$var}[$key]) ? $this->{$var}[$key] : null;
            case ($var === 'env'):
                $key = strtoupper($key);
                return isset($this->env[$key]) ? $this->env[$key] : null;
            case ($var === 'http'):
                if ($key === 'method') {
                    return $this->method();
                }
                if ($key === 'ssl') {
                    return isset($this->env['HTTPS']) ? $this->env['HTTPS'] : false;
                }
                return isset($this->headers[$key]) ? $this->headers[$key] : null;
        }
    }

    /**
     * Provides a simple syntax for making assertions about the properties of a request.
     *
     * The default detectors include the following:
     *
     * - `'mobile'`: Uses a regular expression to match common mobile browser user agents.
     * - `'ajax'`: Checks to see if the `X-Requested-With` header is present, and matches the value
     *    `'XMLHttpRequest'`.
     * - `'flash'`: Checks to see if the user agent is `'Shockwave Flash'`.
     * - `'ssl'`: Verifies that the request is SSL-secured.
     * - `'get'` / `'post'` / `'put'` / `'delete'` / `'head'` / `'options'`: Checks that the HTTP
     *   request method matches the one specified.
     *
     * In addition to the above, this method also accepts media format names to
     * make assertions against the format of the request body (for POST or PUT requests), i.e.
     * `$request->is('json')`. This will return `true` if the client has made a POST request with
     * JSON data.
     *
     * For information about adding custom detectors or overriding the ones in the core, see the
     * `detect()` method.
     *
     * @param  string  $flag The name of the flag to check, which should be the name of a valid
     *                       detector of media format.
     * @return boolean       Returns `true` if the detector check succeeds of the request match
     *                       the media format, otherwise `false`.
     */
    public function is($flag)
    {
        if (!isset($this->_detectors[$flag])) {
            return $flag === $this->format();
        }
        $detector = $this->_detectors[$flag];

        if (is_callable($detector)) {
            return $detector($this);
        }
        if (!is_array($detector)) {
            throw new Exception("Invalid `'{$flag}'` detector definition.");
        }
        list($key, $check) = each($detector);
        $value = $this->attr($key);

        if (is_array($check)) {
            return !!preg_match('~' . join('|', $check) . '~i', $value);
        }
        if (preg_match('~^(?P<char>\~|/|@|#).*?(?P=char)$~', $check)) {
            return !!preg_match($check, $value);
        }
        return $check === $value;
    }

    /**
     * Creates a detector used with `is()`. A detector is a boolean check that is created to
     * determine something about a request.
     *
     * A detector check can be either an exact string match or a regular expression match against a
     * header or environment variable. A detector check can also be a closure that accepts the
     * `Request` object instance as a parameter.
     *
     *
     * @param string $flag     The name of the detector check.
     * @param mixed  $detector Detectors can be specified in the following ways:
     *                         - A key/value array containing a header/environment variable name as key, and a value to match
     *                           against. The value part of the array must be an exact match to the header or
     *                           variable value.
     *                         - A key/value array containing a header/environment variable name as key, and a regular
     *                           expression as value.
     *                         - A closure which accepts an instance of the `Request` object and returns a boolean
     *                           value.
     */
    public function detect($flag, $detector = null)
    {
        if (is_array($flag)) {
            $this->_detectors = $flag + $this->_detectors;
        } else {
            $this->_detectors[$flag] = $detector;
        }
    }

    /**
     * Gets/sets the base path of the current request.
     *
     * @param  string      $basePath The base path to set or none to get the setted one.
     * @return string|self
     */
    public function basePath($basePath = null)
    {
        if (!func_num_args()) {
            return $this->_basePath;
        }
        $this->_basePathTmp = $basePath;
        if ($this->_ignorePath) {
            $basePath = preg_replace($this->_ignorePath, '', $basePath);
        }
        $this->_basePath = $basePath && $basePath !== '/' ? '/' . trim($basePath, '/') : '';
        return $this;
    }

    /**
     * Gets/sets the ignore pattern for the base path.
     *
     * @param  string|array $ignorePath The ignore pattern to set or none to get the setted one.
     * @return mixed
     */
    public function ignorePath($ignorePath = null)
    {
        if (!func_num_args()) {
            return $this->_ignorePath;
        }
        $this->_ignorePath = $ignorePath;
        $this->basePath($this->_basePathTmp);
        return $this;
    }

    /**
     * Sets or returns the current locale string.
     *
     * @param  string      $locale An optional locale string like `'en'`, `'en_US'` or `'de_DE'`.
     *                             If specified, will overwrite the existing locale.
     * @return string|null         Returns the currently set locale string.
     */
    public function locale($locale = null)
    {
        if ($locale) {
            $this->_locale = $locale;
        }
        if ($this->_locale) {
            return $this->_locale;
        }
        if (isset($this->_params['locale'])) {
            return $this->_params['locale'];
        }
    }

    /**
     * Gets/sets the request data.
     *
     * @param  array $data The data to set or none to get the setted one.
     * @return array
     */
    public function data($data = null)
    {
        if (func_num_args() === 1) {
            $this->_data = $data;
            return $this;
        }
        return $this->_data;
    }

    /**
     * Exports a `Request` instance to an array.
     *
     * @param  array $options Options.
     * @return array          The export array.
     */
    public function export($options = [])
    {
        $this->_setContentLength();
        return [
            'basePath' => $this->basePath(),
            'locale'   => $this->locale(),
            'data'     => $this->data(),
            'params'   => $this->params(),
            'env'      => $this->env
        ] + parent::export($options);
    }

    /**
     * Normalizes the data from `$_FILES`.
     *
     * @return array       Normalized data.
     */
    public static function files()
    {
        $data = isset($_FILES) ? $_FILES : [];
        $result = [];

        $normalize = function($key, $value) use ($result){
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

    /**
     * Creates a request extracted from CGI globals.
     *
     * @param  array $config The config array.
     * @return self
     */
    public static function ingoing($config = [])
    {
        $env = isset($config['env']) ? $config['env'] : $_SERVER;

        $defaults = [
            'data'    => static::files() + (isset($_POST) ? $_POST : []),
            'query'   => isset($_GET) ? $_GET : [],
            'cookies' => $_COOKIE,
            'env'     => $env
        ];

        if (!isset($env['REQUEST_URI'])) {
            throw new NetException("Missing `'REQUEST_URI'` environment variable, unable to create the main request.");
        }

        if (!isset($env['SCRIPT_NAME'])) {
            throw new NetException("Missing `'SCRIPT_NAME'` environment variable, unable to create the main request.");
        }

        return new static($config + $defaults);
    }
}
