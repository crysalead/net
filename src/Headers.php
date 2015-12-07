<?php
namespace Lead\Net;

use Exception;
use Lead\Set\Set;

/**
 * Collection of Headers.
 */
class Headers extends \Lead\Collection\Collection
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'Lead\Collection\Collection::toArray',
        'header' => 'Lead\Net\Http\Headers::toHeader'
    ];

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * The constructor
     *
     * @param array $data The data
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'header' => 'Lead\Net\Header'
            ]
        ];
        $config = Set::merge($defaults, $config);
        parent::__construct($config);
        $this->_classes = $config['classes'];

    }

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
            throw new Exception("Error, only string value is allowed.");
        }
        return $this->_data[strtolower($name)] = new $header([
            'name' => $name,
            'data' => [(string) $value]
        ]);
    }

    /**
     * Adds an header.
     *
     * @param  string $value The header string.
     * @return object        The added value.
     */
    public function add($value)
    {
        if (!$value = trim($value)) {
            return;
        }
        $header = $this->_classes['header'];
        if ($parsed = $header::parse($value)) {
            $this->_data[strtolower($parsed->name())] = $parsed;
        } else {
            $this->_data[(string) $value] = true;
        }
    }

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
     * Creates an headers collection.
     *
     * @param  string|array $headers A header string or an array of headers.
     * @return object                A collection of headers.
     */
    public static function parse($headers)
    {
        $collection = new static();
        if (!$headers) {
            return $collection;
        }
        $headers = is_string($headers) ? explode("\n", $headers) : $headers;

        foreach ($headers as $key => $value) {
            if (!$value = trim($value)) {
                continue;
            }
            if (!is_numeric($key)) {
                if (is_array($value)) {
                    $value = "{$key}: " . join(', ', $value);
                } else {
                    $value = "{$key}: {$value}";
                }
            }
            $collection->add($value);
        }
        return $collection;
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
    public static function toHeader($collection)
    {
        $data = [];
        foreach ($collection as $key => $header) {
            if ($header === true) {
                $data[] = $key;
            } elseif ($header = $header->to('header')) {
                $data[] = $header;
            }
        }
        return $data ? join("\n", $data) . "\n\n" : '';
    }
}
