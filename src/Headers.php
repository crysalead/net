<?php
namespace Lead\Net;

use Exception;
use Lead\Net\NetException;
use Lead\Set\Set;

/**
 * Collection of Headers.
 */
abstract class Headers extends \Lead\Collection\Collection
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Assigns a header.
     *
     * @param  string $name  The header name.
     * @param  string $value The header value.
     * @return object        The setted value.
     */
    public function offsetSet($name, $value)
    {
        if (!$name) {
            throw new Exception("Error, invalid header name, can't be empty.");
        }
        $header = $this->_classes['header'];
        if ($value instanceof $header) {
            $value->name($name);
            return $this->_data[strtolower($name)] = $value;
        }
        if (!is_scalar($value)) {
            throw new Exception("Error, only scalar value are allowed as header value.");
        }
        return $this->_data[strtolower($name)] = new $header([
            'name' => $name,
            'data' => [(string) $value]
        ]);
    }

    /**
     * Adds an header.
     *
     * @param  string|array $values  A header string or an array of headers.
     * @param  boolean      $prepend If true, prepend headers to the beginning.
     * @return self
     */
    abstract public function add($values, $prepend = false);

    /**
     * Checks whether or not an header exists.
     *
     * @param  string  $name The header name.
     * @return boolean       Returns `true` if the header exists, `false` otherwise.
     */
    public function offsetExists($name)
    {
        return parent::offsetExists(strtolower($name));
    }

    /**
     * Returns the value of a specific header.
     *
     * @param  string $name The header name.
     * @return object       A Header collection.
     */
    public function offsetGet($name)
    {
        return $this->_data[strtolower($name)];
    }

    /**
     * Unsets an offset.
     *
     * @param string $offset The offset to unset.
     */
    public function offsetUnset($name)
    {
        return parent::offsetUnset(strtolower($name));
    }

    /**
     * Gets the headers as an array.
     *
     * @return array Returns the headers.
     */
    public function data()
    {
        $result = [];
        foreach ($this->_data as $key => $header) {
            $result[] = $header === true ? $key : $header->to('header');
        }
        return $result;
    }

    /**
     * Clear the headers.
     */
    public function clear()
    {
        $this->_data = [];
    }

    /**
     * Returns the headers as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return static::toHeader($this);
    }

    /**
     * Returns the headers as a string.
     *
     * @return string
     */
    /*abstract*/ public static function toHeader($collection){} // https://bugs.php.net/bug.php?id=53081 .
}
