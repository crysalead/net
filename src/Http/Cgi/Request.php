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
    public $params = [];

    /**
     * Data found in the HTTP request body.
     *
     * @var array
     */
    public $data = [];

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
    protected $_ignore = null;

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
            'ignore'    => null,
            'locale'    => null,
            'data'      => [],
            'params'    => [],
            'env'       => [],
            'classes'   => [
                'environment' => 'Lead\Env\Env',
                'auth'        => 'Lead\Net\Http\Auth'
            ]
        ];
        $config = Set::merge($defaults, $config);

        if (!is_object($config['env'])) {
            $environment = $config['classes']['environment'];
            $config['env'] = new $environment($config['env']);
        }

        $config += $this->_defaults($config);
        parent::__construct($config);

        $this->env = $config['env'];
        $this->data = $config['data'];
        $this->params = $config['params'];

        $this->ignore($config['ignore']);
        $this->basePath($config['basePath']);
        $this->locale($config['locale']);

        if (isset($this->data['_method'])) {
            $this->env['REQUEST_METHOD'] = strtoupper($this->data['_method']);
            $this->method($this->env['REQUEST_METHOD']);
            unset($this->data['_method']);
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
            'SCRIPT_NAME'     => '/index.php',
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
            'version'  => $version,
            'scheme'   => strtolower($scheme) . ($env['HTTPS'] ? 's' : ''),
            'host'     => $env['HTTP_HOST'],
            'username' => $username,
            'password' => $password,
            'auth'     => null,
            'ignore'   => '',
            'method'   => $env['REQUEST_METHOD'],
            'headers'  => $headers,
            'env'      => $env
        ];

        $uri = $env['REQUEST_URI'];
        list($path) = explode('?', $uri, 2);

        $scriptName = $env['SCRIPT_NAME'];
        $scriptDir = dirname($scriptName);

        if (stripos($path, $scriptName) === 0) {
            $basePath = $scriptName;
        } elseif (stripos($path, $scriptDir) === 0) {
            $basePath = $scriptDir !== '/' ? $scriptDir : '';
        }

        $basePath = isset($basePath) && $basePath !== '/' ? '/' . trim($basePath, '/') : '';

        return $defaults += [
            'path'     => '/' . (trim(substr($path, strlen($basePath)), '/') ?: '/'),
            'basePath' => $basePath
        ];
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
        if (isset($this->_ignore)) {
            $basePath = str_replace($this->_ignore, '', $basePath);
        }
        $this->_basePath = $basePath && $basePath !== '/' ? '/' . trim($basePath, '/') : '';
        return $this;
    }

    /**
     * Gets/sets the ignore pattern for the base path.
     *
     * @param  string|array $ignore The ignore pattern to set or none to get the setted one.
     * @return mixed
     */
    public function ignore($ignore = null)
    {
        if (!func_num_args()) {
            return $this->_ignore;
        }
        $this->_ignore = $ignore;
        $this->basePath($this->_basePathTmp);
        return $this;
    }

    /**
     * Returns information about the type of content that the client is requesting.
     *
     * @param  boolean $all If `true` lists all accepted content types
     * @return mixed        Returns the negociated type or the accepted content types sorted by
     *                      client preference if `$all` is set to `true`.
     */
    public function accepts()
    {
        $accepts = $this->hasHeader('Accept') ? $this->getHeader('Accept') : ['text/html'];

        foreach ($accepts as $i => $value) {
            list($type, $q) = preg_split('/;\s*q\s*=\s*/', $value, 2) + [$value, 1.0];
            $stars = substr_count($type, '*');
            $score = $stars ? (0.03 - $stars * 0.01) : $q;
            $score = $score * 100000 + strlen($type); //RFC 4288 assumes a max length of 127/127 = 255 chars for mime.
            $preferences[$score][strtolower(trim($type))] = (float) $q;
        }
        krsort($preferences);
        $preferences = call_user_func_array('array_merge', $preferences);
        return $preferences;
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
        if (isset($this->params['locale'])) {
            return $this->params['locale'];
        }
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

    /**
     * Exports a `Request` instance to an array.
     *
     * @param  mixed $request A `Request` instance.
     * @param  array $options Options.
     * @return array          The export array.
     */
    public static function toArray($request, $options = [])
    {
        static::_setContentLength($request);
        return [
            'basePath' => $request->basePath(),
            'locale'   => $request->locale(),
            'data'     => $request->data,
            'params'   => $request->params,
            'env'      => $request->env
        ] + parent::toArray($request);
    }
}
