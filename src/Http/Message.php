<?php
namespace Lead\Net\Http;

use InvalidArgumentException;
use Lead\Set\Set;
use Lead\Net\NetException;

/**
 * HTTP Message class
 */
class Message extends \Lead\Net\Message
{
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
     * The message format.
     *
     * @var string
     */
    protected $_format = null;

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
            'version'  => '1.1',
            'type'     => null,
            'encoding' => null,
            'format'   => null,
            'data'     => null,
            'headers'  => [],
            'classes'  => [
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

        $this->format($config['format']);

        parent::__construct($config);

        if ($config['data'] !== null) {
            $this->set($config['data']);
        }
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

        if (isset($this->headers['Content-Type'])) {
            list($old, $encoding) = explode(';', $this->headers['Content-Type']->value(), 2) + [null, null];
        }

        list($type) = explode(';', $type, 2);
        $this->headers['Content-Type'] = $type . (isset($encoding) ? ';' . $encoding : '');
        return $this;
    }

    /**
     * Gets/sets the format of the request.
     *
     * @param  string      $format A format name.
     * @return string|self
     */
    public function format($format = null)
    {
        if (!$format) {
            return $this->_format;
        }

        $media = $this->_classes['media'];
        if (!$type = $media::type($format)){
            throw new NetException("The `'$format'` format is undefined or has no valid Content-Type defined check the `Media` class.");
        }
        $this->_format = $format;
        $this->type($type);

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
     * Gets the body of this message.
     *
     * @param  array $options The decoding options.
     * @return mixed          The formatted body.
     */
    public function get($options = [])
    {
        $media = $this->_classes['media'];
        $format = $this->format();
        return $format ? $media::decode($format, (string) $this->_body) : (string) $this->_body;
    }

    /**
     * Gets/sets the body of this message.
     *
     * @param  mixed      $value   The formatted body.
     * @param  array      $options The encoding options.
     * @return mixed|self
     */
    public function set($value = null, $options = [])
    {
        $media = $this->_classes['media'];
        $format = $this->format();

        if (!$format && !is_string($value)) {
            throw new NetException("The data must be a string when no format is defined.");
        }
        $this->stream($format ? $media::encode($format, $value, $options) : $value);
        return $this;
    }

    /**
     * Exports a `Message` body to specific format.
     *
     * The supported values of `$format` depend on the `Media` class example:
     *
     * ```php
     * $message->to('xml'); // exports the message body into XML
     * ```
     *
     * @param  string $format  By default the only supported value is `'array'`. However, additional
     *                         format handlers can be registered using the `formats()` method.
     * @param  array  $options Options for converting the collection.
     * @return mixed           The converted collection.
     */
    public function to($format, $options = [])
    {
        $media = $this->_classes['media'];
        if (!$media::get($format)) {
            throw new InvalidArgumentException("Unsupported format `{$format}`.");
        }
        return $media::decode($format, $this->body(), $options);
    }

    /**
     * Magic method to convert the instance into an HTTP message string.
     *
     * @return string
     */
    public function toMessage()
    {
        $this->_setContentLength();
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
     * Auto adds a Content-Length header if necessary.
     */
    protected function _setContentLength()
    {
        if ($this->headers['Transfer-Encoding']->value() === 'chunked') {
            return;
        }
        $length = $this->stream()->length();
        if ($length === null) {
            throw new NetException("A Content-Length header is required but the request stream has a `null` length.");
        }

        $this->headers['Content-Length'] = $this->stream()->length();
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
}
