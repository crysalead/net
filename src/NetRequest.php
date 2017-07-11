<?php
namespace Lead\Net;

use UnexpectedValueException;
use Lead\Net\NetException;
use Lead\Text\Text;
use Lead\Set\Set;
use Lead\Net\Scheme;

/**
 * Low level protocol Request.
 */
class NetRequest extends \Lead\Net\Message
{
    /**
     * The communication protocol
     *
     * @var boolean
     */
    protected $_scheme = 'tcp';

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
     * Absolute path of the message.
     *
     * @var string
     */
    protected $_path = '/';

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
     *                      - `'type'`     _string_ : null
     *                      - `'auth'`     _mixed_  : null
     *                      - `'body'`     _mixed_  : null
     */
    public function __construct($config = [])
    {
        $defaults = [
            'scheme'        => 'tcp',
            'host'          => 'localhost',
            'port'          => null,
            'path'          => '',
        ];

        $config = Set::merge($defaults, $config);

        parent::__construct($config);

        $this->scheme($config['scheme']);
        $this->port($config['port']);
        $this->host($config['host']);
        $this->path($config['path']);
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
     * Returns the message URI.
     *
     * @return string
     */
    public function url()
    {
        $scheme = $this->scheme();
        $scheme = $scheme ? $scheme . '://' : '//';
        return $scheme . $this->host() . $this->path();
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
        return $this;
    }

    public function hostname()
    {
        return $this->_host;
    }

    /**
     * Exports a `Request` instance to an array.
     *
     * @param  mixed $request A `Request` instance.
     * @param  array $options Options.
     * @return array          The export array.
     */
    public function export($options = [])
    {
        return [
            'scheme'   => $this->scheme(),
            'host'     => $this->host(),
            'port'     => $this->port(),
            'path'     => $this->path(),
            'url'      => $this->url(),
            'stream'   => $this->stream()
        ];
    }

    /**
     * Creates a request instance using an absolute URL.
     *
     * @param  string $url    An absolute URL.
     * @param  array  $config The config array.
     * @return self
     */
    public static function create($url = '', $config = [])
    {
        if (func_num_args()) {
            if(!preg_match('~^(?:[a-z]+:)?//~i', $url) || !$defaults = parse_url($url)) {
                throw new NetException("Invalid url: `'{$url}'`.");
            }
        }
        return new static($config + $defaults);
    }
}
