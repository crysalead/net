<?php
namespace Lead\Net\Http;

use Exception;

/**
 * HTTP Header.
 */
class Header extends \Lead\Collection\Collection
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'Lead\Collection\Collection::toArray',
        'header' => 'Lead\Net\Http\Header::toHeader'
    ];

    /**
     * The header name.
     *
     * @var string
     */
    protected $_name = '';

    /**
     * The plain header value.
     *
     * @var string
     */
    protected $_plain = '';

    /**
     * The constructor
     *
     * @param string $name  The header name
     * @param array  $value The header value
     */
    public function __construct($name = '', $value = '')
    {
        if (func_num_args() === 1) {
            $value = $name;
            $name = '';
        }
        $value = is_array($value) ? join(',', $value) : $value;

        $this->_name = $name;
        $this->_plain = $value;

        if (!empty($this->_plain)) {
            $data = array_map('trim', explode(',', $this->_plain));
        } else {
            $data = [];
        }
        parent::__construct($data);
    }

    /**
     * Gets/sets the Header name.
     *
     * @param  string $name The name string.
     * @return string       Returns the name string.
     */
    public function name($name = null)
    {
        if (func_num_args() === 0) {
            return $this->_name;
        }
        return $this->_name = (string) $name;
    }

    /**
     * Assigns a header.
     *
     * @param  string $name  The header name.
     * @param  mixed  $value The header value.
     * @return mixed         The setted value.
     */
    public function offsetSet($name, $value)
    {
        if ($name && !is_numeric($name)) {
            throw new Exception("Invalid index, should be numeric or empty.");
        }
        return $name === null ? $this->_data[] = $value : $this->_data[$name] = $value;
    }

    /**
     * Gets the plain header's value.
     *
     * @return array Returns the headers.
     */
    public function plain()
    {
        return $this->_plain;
    }

    /**
     * Gets the header's value.
     *
     * @return array Returns the headers.
     */
    public function value()
    {
        return join(', ', $this->_data);
    }

    /**
     * Exports the header as an array.
     *
     * @return array Returns the headers.
     */
    public function data()
    {
        return $this->_data;
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
     * Parses a header string value.
     *
     * @param  string $header A header string value.
     * @return object         A header collection.
     */
    public static function parse($value)
    {
        $values = explode(':', $value, 2);
        if (count($values) !== 2) {
            return;
        }

        return new static($values[0], trim($values[1]));
    }

    /**
     * Returns the headers as a string.
     *
     * @return string
     */
    public static function toHeader($collection)
    {
        $data = [];
        foreach ($collection as $value) {
            if ($value) {
                $data[] = $value;
            }
        }
        return $collection->name() . ': ' . join(', ', $data);
    }
}
