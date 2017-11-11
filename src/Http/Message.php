<?php
namespace Lead\Net\Http;

use InvalidArgumentException;
use Lead\Set\Set;
use Lead\Net\NetException;

/**
 * HTTP Message class
 */
class Message
{
    /**
     * HTTP protocol version number
     *
     * @var string
     */
    protected $_version = '1.1';

    /**
     * The stream body.
     *
     * @var object
     */
    protected $_stream = null;

    /**
     * Default chunk size
     *
     * @var array
     */
    protected $_chunkSize = 4096;

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
                'headers' => 'Lead\Net\Http\Headers'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_stream = new MixedPart([
            'mime'    => $config['mime'],
            'charset' => $config['charset'],
            'headers' => $config['headers']
        ]);

        $this->version($config['version']);
        $this->format($config['format']);
        $this->chunkSize($config['chunkSize']);

        if ($config['data'] !== null) {
            $this->set($config['data']);
        } else {
            $this->body($config['body']);
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
     * Get/set the headers instance
     *
     * @param  string $headers The headers instance
     * @return string
     */
    public function headers($headers = null)
    {
        if (!func_num_args()) {
            return $this->_stream->headers();
        }
        $this->_stream->headers($headers);
        return $this;
    }

    /**
     * Get/set the mime.
     *
     * @param  string $mime
     * @return string           The mime.
     */
    public function mime($mime = null)
    {
        if (!func_num_args()) {
            return $this->_stream->mime();
        }
        $this->_stream->mime($mime);
        return $this;
    }

    /**
     * Get/set the charset.
     *
     * @param  string $charset
     * @return string           The charset.
     */
    public function charset($charset = null)
    {
        if (!func_num_args()) {
            return $this->_stream->charset();
        }
        $this->_stream->charset($charset);
        return $this;
    }

    /**
     * Get/set the encoding.
     *
     * @param  string $encoding
     * @return string           The encoding.
     */
    public function encoding($encoding = null)
    {
        if (!func_num_args()) {
            return $this->_stream->encoding();
        }
        $this->_stream->encoding($encoding);
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
     * Get/set the chunk size.
     *
     * @param  integer     $chunkSize The chunk size.
     * @return string|self
     */
    public function chunkSize($chunkSize = null)
    {
        if (!func_num_args()) {
            return $this->_chunkSize;
        }
        $this->_chunkSize = (int) $chunkSize;
        return $this;
    }

    /**
     * Get/set the plain body message.
     *
     * @param  string      $value.
     * @param  array       $options The stream options.
     * @return string|self
     */
    public function body($value = null, $options = [])
    {
        if (!func_num_args()) {
            return $this->_stream->toString();
        }
        $stream = $this->stream();
        $stream->close();
        $stream->add($value, $options);
        return $this;
    }

    /**
     * Get message stream.
     *
     * @return object
     */
    public function stream()
    {
        return $this->_stream;
    }

    /**
     * Gets the body of this message.
     *
     * @param  array $decodeOptions The decoding options.
     * @return mixed                The formatted body.
     */
    public function get($decodeOptions = [])
    {
        $media = $this->_classes['media'];
        $format = $this->format();
        return $format ? $media::decode($format, $this->_stream->toString(), $decodeOptions, $this) : $this->_stream->toString();
    }

    /**
     * Gets/sets the body of this message.
     *
     * @param  mixed      $value         The formatted body.
     * @param  array      $options       The stream options.
     * @param  array      $mediaOptions  The media options.
     * @return mixed|self
     */
    public function set($value = null, $options = [], $mediaOptions = [])
    {
        $media = $this->_classes['media'];
        $format = $this->format();

        if (!$format && !is_string($value)) {
            throw new NetException("The data must be a string when no format/mime is defined.");
        }

        $stream = $this->stream();
        $stream->close();
        $stream->add($format ? $media::encode($format, $value, $mediaOptions, $this) : $value, $options);
        return $this;
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
        return $media::decode($format, $this->body(), $options, $this);
    }

    /**
     * Magic method to convert the instance into an HTTP message string.
     *
     * @return string
     */
    public function toMessage()
    {
        $this->_setContentLength();
        return $this->line() . "\r\n" . $this->headers()->toString() . "\r\n". "\r\n" . $this->toString();
    }

    /**
     * Magic method convert the instance body into a string.
     *
     * @return string
     */
    public function toString()
    {
        $headers = $this->headers();
        if ($headers['Transfer-Encoding']->value() === 'chunked') {
            return $this->toChunks();
        }
        return $this->_stream->toString();
    }

    /**
     * Magic method to convert object to string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
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
        $headers = $this->headers();
        if ($headers['Transfer-Encoding']->value() === 'chunked') {
            return;
        }
        $length = $this->stream()->length();
        if ($length === null) {
            throw new NetException("A Content-Length header is required but the request stream has a `null` length.");
        }

        $headers['Content-Length'] = $this->stream()->length();
    }

    /**
     * Clone the message.
     */
    public function __clone()
    {
        $this->_stream = clone $this->_stream;
    }
}
