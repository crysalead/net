<?php
namespace Lead\Net\Http;

use Lead\Set\Set;
use Lead\Net\NetException;

/**
 * HTTP Message class
 */
class Message extends \Lead\Net\Message
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'   => 'Lead\Net\Message::toArray',
        'string'  => 'Lead\Net\Http\Message::toString',
        'message' => 'Lead\Net\Http\Message::toMessage'
    ];

    /**
     * The headers instance.
     *
     * @var object
     */
    public $headers = null;

    /**
     * HTTP protocol version number
     *
     * @var string
     */
    protected $_version = '1.1';

    /**
     * Adds config values to the public properties when a new object is created.
     *
     * @param array $config Configuration options. Possible values are:
     *                      - `'version'`  _string_ : (defaults `'1.1'`).
     *                      - `'scheme'`   _string_ : (defaults `'http'`).
     *                      - `'type'`     _string_ : (defaults `null`).
     *                      - `'headers'`  _mixed_  : (defaults `[]`).
     */
    public function __construct($config = [])
    {
        $defaults = [
            'version'       => '1.1',
            'type'          => null,
            'encoding'      => null,
            'headers'       => [],
            'classes'       => [
                'auth'    => 'Lead\Net\Http\Auth',
                'media'   => 'Lead\Net\Http\Media',
                'stream'  => 'Lead\Storage\Stream\Stream',
                'headers' => 'Lead\Net\Http\Headers'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];

        $this->version($config['version']);


        if (is_object($config['headers'])) {
            $this->headers = $config['headers'];
        } else {
            $headers = $this->_classes['headers'];
            $this->headers = new $headers(['data' => $config['headers']]);
        }

        if ($config['type']) {
            $this->type($config['type']);
        } elseif (isset($this->headers['Content-Type'])) {
            $this->type($this->headers['Content-Type']->value());
        }
        if ($config['encoding']) {
            $this->encoding($config['encoding']);
        }

        parent::__construct($config);
    }

    /**
     * Gets protocol string.
     *
     * @return string
     */
    public function protocol()
    {
        return "HTTP/" . $this->version();
    }

    /**
     * Gets/sets the HTTP protocol version number.
     *
     * @return string      The version number.
     * @return string|self
     */
    public function version($version = null)
    {
        if (func_num_args() === 0) {
            return $this->_version;
        }
        $this->_version = $version;
        return $this;
    }

    /**
     * Gets/sets the Content-Type.
     *
     * @param  string      $type A full Content-Type i.e. `'application/json'`.
     * @return string|self
     */
    public function type($type = null)
    {
        if (!func_num_args()) {
            if (!isset($this->headers['Content-Type'])) {
                return;
            }
            list($type) = explode(';', $this->headers['Content-Type']->value(), 2);
            return $type;
        }

        if ($type === false) {
            unset($this->headers['Content-Type']);
            return $this;
        }

        $this->headers['Content-Type'] = $type;
        return $this;
    }

    /**
     * Gets/sets the Content-Type charset encoding.
     *
     * @param  string      $charset A charset i.e. `'UTF-8'`.
     * @return string|self
     */
    public function encoding($charset = null)
    {
        if (!isset($this->headers['Content-Type'])) {
            if (func_num_args() !== 0) {
                throw new NetException("Can't set a charset with no valid Content-Type defined.");
            }
            return;
        }
        $value = $this->headers['Content-Type']->value();

        preg_match('/([-\w\/\.+]+)(;\s*?charset=(.+))?/i', $value, $matches);

        if (func_num_args() === 0) {
            return isset($matches[3]) ? strtoupper(trim($matches[3])) : null;
        }
        $this->headers['Content-Type'] = $matches[1] . ($charset ? "; charset=" . strtoupper($charset) : "");
        return $this;
    }

    /**
     * Gets/sets the body of this message.
     *
     * @param  mixed       $value The data to set as body message.
     * @return string|self
     */
    public function body($value = null)
    {
        $media = $this->_classes['media'];
        $type = $this->type();

        if (func_num_args() === 1) {
            $this->stream($type ? $media::encode($type, $value) : $value);
            return $this;
        }
        return $type ? $media::decode($type, (string) $this->_body) : (string) $this->_body;
    }

    /**
     * Magic method to convert the instance into an HTTP message string.
     *
     * @return string
     */
    public function toMessage()
    {
        static::_setContentLength($this);
        return $this->line() . "\r\n" . (string) $this->headers . $this->toString();
    }

    /**
     * Magic method convert the instance body into a string.
     *
     * @return string
     */
    public function toString()
    {
        if ($this->headers['Transfer-Encoding']->value() === 'chunked') {
            $body = '';
            $this->toChunks(function($chunk) use (&$body) { $body .= $chunk; });
            return $body;
        }
        return (string) $this->_body;
    }

    /**
     * Returns the request/status line of the message.
     *
     * @return string
     */
    public function line()
    {
        return '';
    }

    /**
     * Clones the message.
     */
    public function __clone()
    {
        $this->headers = clone $this->headers;
    }

    /**
     * Auto adds a Content-Length header if necessary.
     *
     * @param object $request
     */
    public static function _setContentLength($request)
    {
        if ($request->headers['Transfer-Encoding']->value() === 'chunked') {
            return;
        }
        $length = $request->stream()->length();
        if ($length === null) {
            throw new NetException("A Content-Length header is required but the request stream has a `null` length.");
        }

        $request->headers['Content-Length'] = $request->stream()->length();
    }
}
