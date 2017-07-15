<?php
namespace Lead\Net\Behavior;

use Lead\Set\Set;
use Psr\Http\Message\StreamInterface;

trait HasBodyTrait
{
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
            return (string) $this->_body;
        }
        $this->stream($value, $options);
        return $this;
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
        if (!func_num_args()) {
            return $this->_body;
        }

        if ($this->_body) {
            $this->_body->close();
            $this->_body = null;
        }

        $class = $this->_classes['stream'];

        if ($value instanceof $class) {
            $this->_body = $value;
        } else {
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
}
