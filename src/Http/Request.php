<?php
namespace Lead\Net\Http;

use Lead\Text\Text;
use UnexpectedValueException;

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
     * An array of closures representing various formats this object can be exported to.
     *
     * @var array
     */
    protected $_formats = [];

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
            'method' => 'GET',
            'query' => [],
            'type' => null,
            'auth' => null
        ];
        $config += $defaults;

        $this->_method  = $config['method'];

        $this->_query   = $config['query'];

        parent::__construct($config);

        if (!isset($this->_headers['Host'])) {
            $this->_headers['Host'] = $this->_port ? "{$this->_host}:{$this->_port}" : $this->_host;
        }
        if (!isset($this->_headers['Connection'])) {
            $this->_headers['Connection'] = 'Close';
        }
        if (!isset($this->_headers['User-Agent'])) {
            $this->_headers['User-Agent'] = 'Mozilla/5.0';
        }

        $this->auth($config['auth']);
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
        $this->_method = $method;
        return $this;
    }

    /**
     * Returns the path appended with the query string.
     *
     * @return string
     */
    public function fullPath()
    {
        $query = $request->query() ? '?' . http_build_query($request->query()) : '';
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
     * Gets/sets the  string.
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
     * Magic method to convert object to string.
     *
     * @return string
     */
    public function __toString()
    {
        $status = $this->method() . ' ' . $this->fullPath() . ' ' . $this->protocol() . "\r\n";
        return $status . (string) $this->_headers . (string) $this->_body;
    }

    /**
     * Exports a `Request` instance to an array.
     *
     * @param  mixed $message A `Request` instance.
     * @param  array $options Options.
     * @return array          The export array.
     */
    public static function toArray($request, $options = [])
    {
        $headers = $request->headers();
        if (!in_array($this->method(), ['GET', 'HEAD', 'DELETE'], true)) {
            $headers['Content-Length'] = $this->stream()->length();
        }
        $query = $request->query() ? '?' . http_build_query($request->query()) : '';
        return [
            'method'   => $request->method(),
            'scheme'   => $request->scheme(),
            'version'  => $request->version(),
            'host'     => $request->host(),
            'port'     => $request->port(),
            'path'     => $request->path(),
            'query'    => $query,
            'username' => $request->username(),
            'password' => $request->password(),
            'url'      => $request->url(),
            'headers'  => $headers->data(),
            'stream'   => $this->stream()
        ];
    }
}
