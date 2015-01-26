<?php
namespace net;

use InvalidArgumentException;
use set\Set;

class Message
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'definition' => 'net\Message::toDefinition'
    ];

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
     * The headers instance.
     *
     * @var object
     */
    protected $_headers = null;

    /**
     * The string body of the message.
     *
     * @var string
     */
    protected $_body = '';

    /**
     * Constructor.
     *
     * @param array $config Available configuration options are:
     *                      - `'scheme'`     _string_ : Transfer protocol to use (defaults `'tcp'`).
     *                      - `'port'`       _integer_: Host port (defaults `80`).
     *                      - `'host'`       _string_ : Host name or address (defaults `'localhost'`).
     *                      - `'username'`   _string_ : Username (defaults `null`).
     *                      - `'password'`   _string_ : Password (defaults `null`).
     *                      - `'path'`       _string_ : Absolute path of the request. (defaults `'/'`).
     *                      - `'headers'`    _array_  : The headers array of the message (defaults `''`).
     *                      - `'body'`       _mixed_  : The body string, resource or `storage\stream\Stream` instance
     *                                                  of the message (defaults `''`).
     */
    public function __construct($config = [])
    {
        $defaults = [
            'scheme'   => 'tcp',
            'host'     => 'localhost',
            'port'     => null,
            'username' => null,
            'password' => null,
            'path'     => '',
            'headers'  => [],
            'body'     => '',
            'classes'  => [
                'scheme'  => 'net\Scheme',
                'headers' => 'net\Headers',
                'stream'  => 'storage\stream\Stream'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];

        $this->scheme($config['scheme']);
        $this->host($config['host']);
        $this->port($config['port']);
        $this->username($config['username']);
        $this->password($config['password']);
        $this->path($config['path']);
        $this->headers($config['headers']);
        $this->stream($config['body']);
    }

    /**
     * Gets/sets the body of the message body (string way).
     *
     * @param  string $value.
     * @return string
     */
    public function headers($value = null)
    {
        if (func_num_args() === 0) {
            return $this->_headers;
        }
        $headers = $this->_classes['headers'];
        if (is_object($value)) {
            $this->_headers = $value;
        } else {
            $this->_headers = $headers::parse($value);
        }
        return $this->_headers;
    }

    /**
     * Gets/sets the body of the message body (string way).
     *
     * @param  string $value.
     * @return string
     */
    public function body($value = null)
    {
        if (func_num_args() === 1) {
            $this->_body = $this->stream($value);
        }
        return (string) $this->_body;
    }

    /**
     * Gets/sets the body of the message body (stream way).
     *
     * @param  mixed  $value   A stream object or stream resource.
     * @param  array  $options The stream options.
     * @return object
     */
    public function stream($value = null, $options = [])
    {
        if (func_num_args() === 0) {
            return $this->_body;
        }
        $stream = $this->_classes['stream'];
        return $this->_body = new $stream(['data' => $value] + $options);
    }

    /**
     * Gets/sets the scheme.
     *
     * @param  string $scheme The scheme of the message
     * @return string
     */
    public function scheme($scheme = null)
    {
        if (func_num_args() === 0) {
            return $this->_scheme;
        }
        return $this->_scheme = $scheme;
    }

    /**
     * Gets/sets the host.
     *
     * @param  string $host The host of the message
     * @return string
     */
    public function host($host = null)
    {
        if (func_num_args() === 0) {
            return $this->_host;
        }
        return $this->_host = $host;
    }

    /**
     * Gets/sets the port.
     *
     * @param  string $port The port of the message.
     * @return string
     */
    public function port($port = null)
    {
        if (func_num_args() === 1) {
            return $this->_port = $port;
        }
        if ($this->_port !== null) {
            return $this->_port;
        }
        $scheme = $this->_classes['scheme'];
        $name = $this->scheme();

        if ($scheme::registered($name)) {
            return $scheme::port($name);
        }
        return $this->_port;
    }

    /**
     * Gets/sets the path.
     *
     * @param  string $path Absolute path of the message.
     * @return string
     */
    public function path($path = null)
    {
        if (func_num_args() === 0) {
            return $this->_path;
        }
        return $this->_path = '/' . ltrim($path, '/');
    }

    /**
     * Gets/sets the username.
     *
     * @param  string $path The username of the message.
     * @return string
     */
    public function username($username = null)
    {
        if (func_num_args() === 0) {
            return $this->_username;
        }
        return $this->_username = $username;
    }

    /**
     * Gets/sets the password.
     *
     * @param  string $path The password of the message.
     * @return string
     */
    public function password($password = null)
    {
        if (func_num_args() === 0) {
            return $this->_password;
        }
        return $this->_password = $password;
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
     * Accessor method for adding format handlers to `Message` instances.
     *
     * The values assigned are used by `Message::to()` to convert `Message` instances into
     * different formats, i.e. array.
     *
     * This can be accomplished in two ways. First, format handlers may be registered on a
     * case-by-case basis, as in the following:
     *
     * ```php
     * Message::formats('array', function($message, $options) {
     * ...
     * });
     *
     * // You can also implement the above as a static class method, and register it as follows:
     * Message::formats('array', 'my\custom\Formatter::toArray');
     * ```
     *
     * @see    net\Message::to()
     * @param  string $format  A string representing the name of the format that a `Message`
     *                         can be converted to. If `false`, reset the `$_formats` attribute.
     *                         If `null` return the content of the `$_formats` attribute.
     * @param  mixed  $handler The function that handles the conversion, either an anonymous function,
     *                         a fully namespaced class method or `false` to remove the `$format` handler.
     * @return mixed
     */
    public static function formats($format = null, $handler = null)
    {
        if ($format === null) {
            return static::$_formats;
        }
        if ($format === false) {
            return static::$_formats = ['definition' => 'net\Message::toDefinition'];
        }
        if ($handler === false) {
            unset(static::$_formats[$format]);
            return;
        }
        return static::$_formats[$format] = $handler;
    }

    /**
     * Exports a `Message` object to another format.
     *
     * The supported values of `$format` depend on the registered handlers.
     *
     * Once the appropriate handlers are registered, a `Collection` instance can be converted into
     * any handler-supported format, i.e.:
     *
     * ```php
     * $message->to('array'); // returns a Array string
     * ```
     *
     * @see    net\Message::formats()
     * @param  string $format  By default the only supported value is `'array'`. However, additional
     *                         format handlers can be registered using the `formats()` method.
     * @param  array  $options Options for converting the collection.
     * @return mixed           The converted collection.
     */
    public function to($format, $options = [])
    {
        if (!is_string($format) || !isset(static::$_formats[$format])) {
            if (is_callable($format)) {
                return $format($this, $options);
            }
            throw new InvalidArgumentException("Unsupported format `{$format}`.");
        }
        $handler = static::$_formats[$format];
        return is_string($handler) ? call_user_func($handler, $this, $options) : $handler($this, $options);
    }

    /**
     * Exports a `Message` instance to an array.
     *
     * @param  mixed $message A `Message` instance.
     * @param  array $options Options used to export `$message`.
     * @return array          The exported array.
     */
    public static function toDefinition($message, $options = [])
    {
        return [
            'scheme'   => $message->scheme(),
            'host'     => $message->host(),
            'port'     => $message->port(),
            'username' => $message->username(),
            'password' => $message->password(),
            'path'     => $message->path(),
            'url'      => $message->url()
        ];
    }

    /**
     * Magic method to convert object to string.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->_headers . "\n" . (string) $this->_body;
    }
}
