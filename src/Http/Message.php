<?php
namespace Lead\Net\Http;

use Lead\Set\Set;
use Lead\Net\NetException;

/**
 * HTTP Message class
 */
abstract class Message extends \Lead\Net\Message
{
    /**
     * HTTP protocol version number
     *
     * @var string
     */
    protected $_version = '1.1';

    /**
     * The headers instance.
     *
     * @var object
     */
    protected $_headers = null;

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
                'stream'  => 'Lead\Storage\Stream\Stream'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];

        $this->version($config['version']);
        $this->headers($config['headers']);

        if ($config['type']) {
            $this->type($config['type']);
        } elseif (isset($this->_headers['Content-Type'])) {
            $this->type($this->_headers['Content-Type']);
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
        if (func_num_args() === 0) {
            if (!isset($this->_headers['Content-Type'])) {
                return;
            }
            list($type) = explode(';', $this->_headers['Content-Type']->value(), 2);
            return $type;
        }

        if ($type === false) {
            unset($this->_headers['Content-Type']);
            return $this;
        }

        $this->_headers['Content-Type'] = $type;
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
        if (!isset($this->_headers['Content-Type'])) {
            if (func_num_args() !== 0) {
                throw new NetException("Can't set a charset with no valid Content-Type defined.");
            }
            return;
        }
        $value = $this->_headers['Content-Type']->value();

        preg_match('/([-\w\/\.+]+)(;\s*?charset=(.+))?/i', $value, $matches);

        if (func_num_args() === 0) {
            return isset($matches[3]) ? strtoupper(trim($matches[3])) : null;
        }
        $this->_headers['Content-Type'] = $matches[1] . ($charset ? "; charset=" . strtoupper($charset) : "");
        return $this;
    }

    /**
     * Gets/sets the body of the message body (string way).
     *
     * @param  string      $value.
     * @return string|self
     */
    public function headers($value = null)
    {
        if (func_num_args() === 0) {
            if (!$this->_headers) {
                $headers = $this->_classes['headers'];
                $this->_headers = new $headers();
            }
            return $this->_headers;
        }
        if (is_object($value)) {
            $this->_headers = $value;
        } else {
            $this->headers()->add($value);
        }
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
     * Magic method to convert object to string.
     *
     * @return string
     */
    public function toString()
    {
        static::_setContentLength($this);
        $headers = $this->headers();
        if (isset($headers['Transfer-Encoding']) && $headers['Transfer-Encoding']->value() === 'chunked') {
            $content = '';
            $this->toChunks(function($chunk) use (&$content) { $content .= $chunk; });
            return $content;
        }
        return $this->line() . "\r\n" . (string) $this->_headers . (string) $this->_body;
    }

    /**
     * Flushes the content of a Message chunk by chunk.
     *
     * @param Closure $closure The process closure.
     * @param Closure $size    The size of the chunks to process.
     */
    public function toChunks($closure, $size = null)
    {
        $size = $size > 0 ? $size : $this->chunkSize();
        $stream = $this->stream();
        $headers = $this->line() . "\r\n" . (string) $this->_headers;
        $closure($headers, strlen($headers));
        while($chunk = $stream->read($size)) {
            $readed = strlen($chunk);
            $closure(dechex($readed) . "\r\n" . $chunk . "\r\n", $readed);
        }
        $closure("0\r\n", 0);
        if ($stream->seekable()) {
            $stream->rewind();
        }
    }

    /**
     * Auto adds a Content-Length header if necessary.
     *
     * @param object $request
     */
    public static function _setContentLength($request)
    {
        $headers = $request->headers();
        if (isset($headers['Content-Length']) || isset($headers['Transfer-Encoding']) && $headers['Transfer-Encoding']->value() === 'chunked') {
            return;
        }
        $length = $request->stream()->length();
        if ($length === null) {
            throw new NetException("A Content-Length header is required but the request stream has a `null` length.");
        }
        $headers['Content-Length'] = $request->stream()->length();
    }

    /**
     * Returns the request/status line of the message.
     *
     * @return string
     */
    abstract public function line();
}
