<?php
namespace Lead\Net;

use Lead\Set\Set;

class Message
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * The stream body.
     *
     * @var object
     */
    protected $_body = null;

    /**
     * Default chunk size
     *
     * @var array
     */
    protected $_chunkSize = 4096;

    /**
     * Constructor.
     *
     * @param array $config Available configuration options are:
     *                      - `'body'`       _mixed_  : The body string, resource or `storage\stream\Stream` instance
     *                                                  of the message (defaults `''`).
     *
     */
    public function __construct($config = [])
    {
        $defaults = [
            'body'      => '',
            'chunkSize' => 256,
            'classes'   => [
                'scheme'  => 'Lead\Net\Scheme',
                'stream'  => 'Lead\Storage\Stream\Stream'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];

        $this->chunkSize($config['chunkSize']);
        $this->body($config['body']);
    }

    /**
     * Gets/sets the plain body message.
     *
     * @param  string      $value.
     * @return string|self
     */
    public function body($value = null)
    {
        if (func_num_args() === 1) {
            $this->stream($value);
            return $this;
        }
        return (string) $this->_body;
    }

    /**
     * Gets/sets the body of the message body (stream way).
     *
     * @param  mixed       $value   A stream object or stream resource.
     * @param  array       $options The stream options.
     * @return string|self
     */
    public function stream($value = null, $options = [])
    {
        if (func_num_args() === 0) {
            return $this->_body;
        }
        if (is_object($value)) {
            $this->_body = $value;
        } else {
            $stream = $this->_classes['stream'];
            $this->_body = new $stream(['data' => $value] + $options);
        }
        return $this;
    }

    /**
     * Gets/sets the chunk size.
     *
     * @param  integer     $chunkSize The chunk size.
     * @return string|self
     */
    public function chunkSize($chunkSize = null)
    {
        if (func_num_args() === 0) {
            return $this->_chunkSize;
        }
        $this->_chunkSize = $chunkSize;
        return $this;
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
        while($chunk = $stream->read($size)) {
            $readed = strlen($chunk);
            if ($closure(dechex($readed) . "\r\n" . $chunk . "\r\n", $readed) === false) {
                break;
            }
        }
        $closure("0\r\n\r\n", 0);
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
    }

    /**
     * Magic method to convert object to string.
     *
     * @return string
     */
    public function toString()
    {
        return (string) $this->_body;
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
     * Exports a `Message` instance to an array.
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
}
