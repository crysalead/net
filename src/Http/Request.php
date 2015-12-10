<?php
namespace Lead\Net\Http;

use UnexpectedValueException;
use Lead\Net\NetException;
use Lead\Text\Text;
use Lead\Net\Scheme;

/**
 * Facilitates HTTP request creation by assembling connection and path info, `GET` and `POST` data,
 * and authentication credentials in a single, stateful object.
 */
class Request extends \Lead\Net\Http\Message
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'   => 'Lead\Net\Http\Request::toArray'
    ];

    /**
     * The method of the request, typically one of the following: `GET`, `POST`, `PUT`, `DELETE`,
     * `OPTIONS`, `HEAD`, `TRACE` or `CONNECT`.
     *
     * @var string
     */
    protected $_method = 'GET';

    /**
     * Key/value pairs found encoded in the URL after the '?'.
     *
     * @var array
     */
    protected $_query = [];

    /**
     * Authentication type and parameters for HTTP Basic or Digest.
     *
     * Any array with a 'nonce' attribute implies Digest authentication; all other non-empty values
     * for imply Basic authentication.
     *
     * @see lithium\net\http\Auth::encode()
     * @var mixed
     */
    protected $_auth = null;

    /**
     * Adds config values to the public properties when a new object is created.
     *
     * @param array $config Configuration options:
     *                      - `'version'`  _string_ : '1.1'
     *                      - `'method'`   _string_ : 'GET'
     *                      - `'scheme'`   _string_ : 'http'
     *                      - `'host'`     _string_ : 'localhost'
     *                      - `'port'`     _integer_: null
     *                      - `'username'` _string_ : null
     *                      - `'password'` _string_ : null
     *                      - `'path'`     _string_ : null
     *                      - `'query'`    _array_  : []
     *                      - `'headers'`  _array_  : []
     *                      - `'type'`     _string_ : null
     *                      - `'auth'`     _mixed_  : null
     *                      - `'body'`     _mixed_  : null
     */
    public function __construct($config = [])
    {
        $defaults = [
            'scheme'   => 'http',
            'host'     => 'localhost',
            'port'     => null,
            'username' => null,
            'password' => null,
            'method'   => 'GET',
            'path'     => '',
            'query'    => [],
            'auth'     => null,
            'cookies'  => []
        ];
        $config += $defaults;

        parent::__construct($config);

        if (!isset($this->_headers['User-Agent'])) {
            $this->_headers->add('User-Agent: Mozilla/5.0', true);
        }
        if (!isset($this->_headers['Connection'])) {
            $this->_headers->add('Connection: Close', true);
        }

        $this->scheme($config['scheme']);
        $this->port($config['port']);
        $this->host($config['host']);
        $this->username($config['username']);
        $this->password($config['password']);
        $this->method($config['method']);
        $this->path($config['path']);
        $this->query($config['query']);
        $this->auth($config['auth']);

        $cookies = $this->headers()->cookies();
        foreach ($config['cookies'] as $key => $value) {
            $cookies[$key] = $value;
        }
    }

    /**
     * Returns the status line.
     *
     * @return string
     */
    public function line()
    {
        return $this->method() . ' ' . $this->fullPath() . ' ' . $this->protocol();
    }

    /**
     * Gets/sets the scheme.
     *
     * @param  string      $scheme The scheme of the message
     * @return string|self
     */
    public function scheme($scheme = null)
    {
        if (func_num_args() === 0) {
            return $this->_scheme;
        }
        $this->_scheme = $scheme;
        return $this;
    }

    /**
     * Gets/sets the port.
     *
     * @param  string      $port The port of the message.
     * @return string|self
     */
    public function port($port = null)
    {
        if (func_num_args() === 1) {
            $this->_port = $port;
            return $this;
        }
        if ($this->_port !== null) {
            return $this->_port;
        }
        $scheme = $this->_classes['scheme'];
        $name = $this->scheme();

        if ($scheme::registered($name)) {
            return $scheme::port($name);
        }
    }

    /**
     * Gets/sets the path.
     *
     * @param  string      $path Absolute path of the message.
     * @return string|self
     */
    public function path($path = null)
    {
        if (func_num_args() === 0) {
            return $this->_path;
        }
        $this->_path = '/' . ltrim($path, '/');
        return $this;
    }

    /**
     * Gets/sets the method.
     *
     * @param  string $method The method name to set.
     * @return mixed
     */
    public function method($method = null)
    {
        if (func_num_args() === 0) {
            return $this->_method;
        }
        $this->_method = strtoupper($method);
        return $this;
    }

    /**
     * Gets/sets the username.
     *
     * @param  string      $path The username of the message.
     * @return string|self
     */
    public function username($username = null)
    {
        if (func_num_args() === 0) {
            return $this->_username;
        }
        $this->_username = $username;
        return $this;
    }

    /**
     * Gets/sets the password.
     *
     * @param  string      $path The password of the message.
     * @return string|self
     */
    public function password($password = null)
    {
        if (func_num_args() === 0) {
            return $this->_password;
        }
        $this->_password = $password;
        return $this;
    }

    /**
     * Returns the message url.
     *
     * @return string
     */
    public function url()
    {
        $scheme = $this->_classes['scheme'];
        $name = $this->scheme();
        $port = $this->port();

        if ($scheme::registered($name)) {
            $port = $port === $scheme::port($name) ? null : $port;
        }

        $port = $port ? ':' . $port : '';
        $scheme = $name ? $name . '://' : '//';
        $credentials = '';
        if ($username = $this->username()) {
            $credentials = $username;
            if ($password = $this->password()) {
                $credentials .= ':' . $password;
            }
            $credentials .= '@';
        }

        return $scheme . $credentials . $this->host() . $port . $this->path();
    }

    /**
     * Gets/sets the host.
     *
     * @param  string      $host The host of the message
     * @return string|self
     */
    public function host($host = null)
    {
        if (func_num_args() === 0) {
            return $this->_host;
        }
        $this->_host = $host;
        $port = Scheme::port($this->_scheme) !== $this->_port ? $this->_port : '';
        $this->headers()->add('Host: ' . ($port ? "{$this->_host}:{$port}" : $this->_host), true);
        return $this;
    }

    /**
     * Returns the path appended with the query string.
     *
     * @return string
     */
    public function fullPath()
    {
        $query = $this->query() ? '?' . http_build_query($this->query()) : '';
        return $this->path() . $query;
    }

    /**
     * Sets the request authorization.
     *
     * @param  mixed  $auth Any array with a 'nonce' attribute implies Digest authentication. Defaults to Basic authentication otherwise.
     *                      If `false` the Authorization header will be removed.
     * @return mixed
     */
    public function auth($auth = true)
    {
        if (!$auth) {
            unset($this->_headers['Authorization']);
            return;
        }
        if (is_array($auth) && !empty($auth['nonce'])) {
            $data = ['method' => $this->method(), 'uri' => $this->path()];
            $data += $auth;
        } else {
            $data = [];
        }
        $auth = $this->_classes['auth'];
        $data = $auth::encode($this->username(), $this->password(), $data);
        $this->_headers['Authorization'] = $auth::header($data);
        return $this;
    }

    /**
     * Gets/sets the query string.
     *
     * @param  array  $qs The query string to set or none to get the defined one.
     * @return string
     */
    public function query($qs = null)
    {
        if (func_num_args() === 0) {
            return $this->_query;
        }
        $this->_query = $qs + $this->_query;
        return $this;
    }

    /**
     * Auto adds a Content-Length header if necessary.
     *
     * @param object $request
     */
    public static function _setContentLength($request)
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'DELETE'], true)) {
            return;
        }
        parent::_setContentLength($request);
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
            'method'   => $request->method(),
            'scheme'   => $request->scheme(),
            'version'  => $request->version(),
            'host'     => $request->host(),
            'port'     => $request->port(),
            'path'     => $request->path(),
            'query'    => $request->query() ? '?' . http_build_query($request->query()) : '',
            'username' => $request->username(),
            'password' => $request->password(),
            'url'      => $request->url(),
            'headers'  => $request->headers(),
            'stream'   => $request->stream()
        ];
    }
}
