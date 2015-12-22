<?php
namespace Lead\Net\Http;

use UnexpectedValueException;
use Lead\Net\NetException;
use Lead\Text\Text;
use Lead\Set\Set;
use Lead\Net\Scheme;

/**
 * Facilitates HTTP request creation by assembling connection and path info, `GET` and `POST` data,
 * and authentication credentials in a single, stateful object.
 */
class Request extends \Lead\Net\Http\Message implements \Psr\Http\Message\RequestInterface
{
    use Psr7\MessageTrait, Psr7\RequestTrait;

    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array' => 'Lead\Net\Http\Request::toArray'
    ];

    /**
     * The request's mode.
     *
     * @var string
     */
    protected $_mode = null;

    /**
     * The method of the request, typically one of the following: `GET`, `POST`, `PUT`, `DELETE`,
     * `OPTIONS`, `HEAD`, `TRACE` or `CONNECT`.
     *
     * @var string
     */
    protected $_method = 'GET';

    /**
     * The communication protocol
     *
     * @var boolean
     */
    protected $_scheme = 'http';

    /**
     * The port number, `null` for auto.
     *
     * @var integer
     */
    protected $_port = null;

    /**
     * The hostname.
     *
     * @var string
     */
    protected $_host = 'localhost';

    /**
     * The username.
     *
     * @var boolean
     */
    protected $_username = null;

    /**
     * The password.
     *
     * @var string
     */
    protected $_password = null;

    /**
     * Absolute path of the message.
     *
     * @var string
     */
    protected $_path = '/';

    /**
     * Key/value pairs found encoded in the URL after the '?'.
     *
     * @var array
     */
    protected $_query = [];

    /**
     * Fragement found in the URL after the '#'.
     *
     * @var array
     */
    protected $_fragment = '';

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
            'scheme'        => 'http',
            'host'          => 'localhost',
            'port'          => null,
            'username'      => null,
            'password'      => null,
            'method'        => 'GET',
            'path'          => '',
            'query'         => [],
            'fragment'      => '',
            'auth'          => null,
            'cookies'       => [],
            'mode'          => 'origin',
            'classes' => [
                'cookies' => 'Lead\Net\Http\Cookie\Cookies'
            ]
        ];

        $config = Set::merge($defaults, $config);

        parent::__construct($config);

        if (!isset($this->headers['User-Agent'])) {
            $this->headers->prepend('User-Agent', 'Mozilla/5.0');
        }
        if (!isset($this->headers['Connection'])) {
            $this->headers->prepend('Connection', 'Close');
        }

        $this->mode($config['mode']);
        $this->scheme($config['scheme']);
        $this->port($config['port']);
        $this->host($config['host']);
        $this->username($config['username']);
        $this->password($config['password']);
        $this->method($config['method']);
        $this->path($config['path']);
        $this->query($config['query']);
        $this->fragment($config['fragment']);
        $this->auth($config['auth']);

        $cookies = $this->_classes['cookies'];
        $this->headers->cookies = new $cookies(['data' => $config['cookies']]);
    }

    /**
     * Gets/sets the request's mode.
     *
     * @param  string      $mode The mode of the message
     * @return string|self
     */
    public function mode($mode = null)
    {
        if (func_num_args() === 0) {
            return $this->_mode;
        }
        $this->_mode = $mode;
        return $this;
    }

    /**
     * Returns the status line.
     *
     * @return string
     */
    public function line()
    {
        return $this->method() . ' ' . $this->requestTarget() . ' ' . $this->protocol();
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
        if (!func_num_args()) {
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
        $this->_username = $username ?: null;
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
        $this->_password = $password ?: null;
        return $this;
    }

    /**
     * Returns the message URI.
     *
     * @return string
     */
    public function url()
    {
        $scheme = $this->scheme();
        $scheme = $scheme ? $scheme . '://' : '//';
        $query = $this->query() ? '?' . http_build_query($this->query()) : '';
        $fragment = $this->fragment() ? '#' . $this->fragment() : '';
        return $scheme . $this->host() . $this->path() . $query . $fragment;
    }

    /**
     * Returns the credential.
     *
     * @return string|null The credential string.
     */
    public function credential()
    {
        if (!$username = $this->username()) {
            return '';
        }
        $credentials = $username;
        if ($password = $this->password()) {
            $credentials .= ':' . $password;
        }
        return $credentials;
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
            $scheme = $this->_classes['scheme'];
            $port = $scheme::port($this->_scheme) !== $this->_port ? $this->_port : null;
            return ($port !== null ? "{$this->_host}:{$port}" : $this->_host);
        }
        if (strpos($host, ':')) {
            list($host, $port) = explode(':', $host);
            $this->port($port);
        }
        $this->_host = $host;
        $this->headers->prepend('Host', $this->host());
        return $this;
    }

    public function hostname()
    {
        return $this->_host;
    }

    /**
     * Returns the request target present in the status line.
     *
     * @return string
     */
    public function requestTarget()
    {
        if ($this->method() === 'CONNECT' ||  $this->mode() === 'authority') {
            $credential = $this->credential();
            return ($credential ? $credential. '@' : '') . $this->host();
        }
        if ($this->mode() === 'absolute') {
            return $this->url();
        }
        if ($this->mode() === 'asterisk') {
            return '*';
        }
        $query = $this->query() ? '?' . http_build_query($this->query()) : '';
        $fragment = $this->fragment();
        return $this->path() . $query . ($fragment ? '#' . $fragment : '');
    }

    /**
     * Sets the request authorization.
     *
     * @param  mixed  $auth Any array with a 'nonce' attribute implies Digest authentication.
     *                      Defaults to Basic authentication otherwise.
     *                      If `false` the Authorization header will be removed.
     * @return mixed
     */
    public function auth($auth = true)
    {
        if ($auth === false) {
            unset($this->headers['Authorization']);
        }
        if (!$auth) {
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
        $this->headers['Authorization'] = $auth::header($data);
        return $this;
    }

    /**
     * Gets/sets the query string.
     *
     * @param  string|array $qs The query string to set or none to get the defined one.
     * @return string
     */
    public function query($qs = null)
    {
        if (func_num_args() === 0) {
            return $this->_query;
        }
        if (is_string($qs)) {
            parse_str($qs, $qs);
        }
        $this->_query = $qs + $this->_query;
        return $this;
    }

    /**
     * Gets/sets the fragment string.
     *
     * @param  string $fragment The fragment string to set or none to get the defined one.
     * @return string
     */
    public function fragment($fragment = null)
    {
        if (func_num_args() === 0) {
            return $this->_fragment;
        }
        $this->_fragment = $fragment;
        return $this;
    }

    /**
     * Creates a request instance using an absolute URL.
     *
     * @param  string $url    An absolute URL.
     * @param  array  $config The config array.
     * @return self
     */
    public static function parseUrl($url = null, $config = [])
    {
        if (func_num_args()) {
            if(!preg_match('~^(?:[a-z]+:)?//~i', $url) || !$defaults = parse_url($url)) {
                throw new NetException("Invalid url: `'{$url}'`.");
            }
            $defaults['username'] = isset($defaults['user']) ? $defaults['user'] : null;
            $defaults['password'] = isset($defaults['pass']) ? $defaults['pass'] : null;
        }
        return new static($config + $defaults);
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
            'fragment' => $request->fragment(),
            'username' => $request->username(),
            'password' => $request->password(),
            'url'      => $request->url(),
            'stream'   => $request->stream()
        ];
    }
}
