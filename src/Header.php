<?php
namespace Lead\Net;

use Exception;

/**
 * Collection of Header values.
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
        'header' => 'Lead\Net\Header::toHeader'
    ];

    /**
     * The header name.
     *
     * @var string
     */
    protected $_name = null;

    /**
     * The constructor
     *
     * @param array $data The data
     */
    public function __construct($config = [])
    {
        if (isset($config['data'])) {
            $config['data'] = $config['data'] !== null ? (array) $config['data'] : [];
        }
        parent::__construct($config);
        if (isset($config['name'])) {
            $this->_name = $config['name'];
        }
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
     * Gets the headers as an array.
     *
     * @return array Returns the headers.
     */
    public function data()
    {
        return join(', ', $this->_data);
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
        $data = array_map('trim', explode(',', $values[1]));

        return new static([
            'name'  => $values[0],
            'data' => $data
        ]);
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
