<?php
namespace Lead\Net;

use Lead\Set\Set;
use Psr\Http\Message\StreamInterface;

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
    protected $_chunkSize = 256;

    /**
     * Constructor.
     *
     * @param array $config Available configuration options are:
     *                      - `'chunkSize'` _integer_ : The chunck size (defaults `256`).
     *                      - `'body'`      _mixed_   : The body string, resource or `storage\stream\Stream` instance
     *                                                   of the message (defaults `''`).
     *                      - `'classes'`  _array_  : class dependencies.
     *
     */
    public function __construct($config = [])
    {
        $defaults = [
            'chunkSize' => 256,
            'body'      => '',
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
     * Get/set the chunk size.
     *
     * @param  integer     $chunkSize The chunk size.
     * @return string|self
     */
    public function chunkSize($chunkSize = null)
    {
        if (func_num_args() === 0) {
            return $this->_chunkSize;
        }
        $this->_chunkSize = (int) $chunkSize;
        return $this;
    }

    /**
     * Get/set the plain body message.
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
     * Get/set the body of the message body (stream way).
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

        if ($this->_body) {
            $this->_body->close();
            $this->_body = null;
        }

        if ($value instanceof StreamInterface) {
            $this->_body = $value;
        } else {
            $class = $this->_classes['stream'];
            $this->_body = new $class(['data' => $value] + $options);
        }
        return $this;
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
    }
}
