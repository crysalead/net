<?php
namespace Lead\Net\Http;

use InvalidArgumentException;
use Lead\Set\Set;
use Lead\Net\NetException;
use Lead\Net\Behavior\HasBodyTrait;
use Lead\Net\Behavior\HasContentTypeTrait;

/**
 * HTTP Message class
 */
class Message
{
    use HasBodyTrait, HasContentTypeTrait;

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
    public $headers = null;

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
     *                      - `'mime'`     _string_ : (defaults `null`).
     *                      - `'headers'`  _mixed_  : (defaults `[]`).
     *                      - `'classes'`  _array_  : class dependencies.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'version'   => '1.1',
            'mime'      => null,
            'charset'   => null,
            'format'    => null,
            'data'      => null,
            'body'      => '',
            'chunkSize' => 256,
            'headers'   => [],
            'classes'   => [
                'scheme'  => 'Lead\Net\Scheme',
                'auth'    => 'Lead\Net\Http\Auth',
                'media'   => 'Lead\Net\Http\Media',
                'stream'  => 'Lead\Net\Mime\Stream\MimeStream',
                'headers' => 'Lead\Net\Http\Headers'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $class = $this->_classes['headers'];

        $headers = $config['headers'];
        $this->headers = is_object($headers) ? $headers : new $class(['data' => $headers]);

        $this->version($config['version']);

        $this->_initContentType($config['mime'], $config['charset']);

        $this->format($config['format']);

        $this->chunkSize($config['chunkSize']);
        $this->body($config['body']);

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
     * Gets/sets the format of the request.
     *
     * @param  string      $format A format name.
     * @return string|self
     */
    public function format($format = null)
    {
        $media = $this->_classes['media'];

        if (!func_num_args()) {
            if ($this->_format === null) {
                $this->_format = $media::suitable($this);
            }
            return $this->_format;
        }
        if ($format === null) {
            return $this;
        }

        if (!$mime = $media::mime($format)){
            throw new NetException("The `'{$format}'` format is undefined or has no valid Content-Type defined check the `Media` class.");
        }
        $this->_format = $format;
        $this->mime($mime);

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
        return $format ? $media::decode($format, (string) $this->_body, $options) : (string) $this->_body;
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
            return $this->toChunks();
        }
        return (string) $this->_body;
    }

    /**
     * Flush the content of a Message chunk by chunk.
     *
     * @param Closure $size The size of the chunks to process.
     */
    public function toChunks($size = null)
    {
        $body = '';
        $size = $size > 0 ? $size : $this->chunkSize();
        $stream = $this->stream();
        while($chunk = $stream->read($size)) {
            $readed = strlen($chunk);
            if (!$readed) {
                break;
            }
            $body .= dechex($readed) . "\r\n" . $chunk . "\r\n";
        }
        $body .= "0\r\n\r\n";

        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        return $body;
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
     * Export a `Message` instance to an array.
     *
     * @param  array $options Options used to export `$message`.
     * @return array          The export array.
     */
    public function export($options = [])
    {
        return [
            'body' => $this->stream()
        ];
    }

    /**
     * Clone the message.
     */
    public function __clone()
    {
        $this->_body = clone $this->_body;
        $this->headers = clone $this->headers;
    }
}
