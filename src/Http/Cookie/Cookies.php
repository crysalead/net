<?php
namespace Lead\Net\Http\Cookie;

use Exception;
use Generator;
use Lead\Set\Set;

/**
 * Collection of `Cookie`.
 */
class Cookies extends \Lead\Collection\Collection
{
    const NAME = 'Cookie';

    /**
     * Class dependencies
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'value' => 'Lead\Net\Http\cookie\Cookies::toValue',
        'array'  => 'Lead\Net\Http\cookie\Cookies::toArray',
        'header' => 'Lead\Net\Http\cookie\Cookies::toHeader'
    ];

    /**
     * Constructor
     *
     * @param $config The config. Possible values are:
     *                - `'classes'`  _array_ : The class dependencies.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'data'    => [],
            'classes' => [
                'header' => 'Lead\Net\Http\Header',
                'cookie' => 'Lead\Net\Http\Cookie\Cookie'
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_classes = $config['classes'];

        foreach ($config['data'] as $key => $value) {
            $this[$key] = $value;
        }
    }

    /**
     * Adds a cookie to the collection.
     *
     * @param  string $name  The cookie name.
     * @param  object $value The cookie.
     * @return object        The setted cookie.
     */
    public function offsetSet($name, $value)
    {
        $cookie = $this->_classes['cookie'];
        if (!$cookie::isValidName($name)) {
            throw new Exception("Invalid Cookie name `'{$name}'`.");
        }
        if (!is_object($value)) {
            $value = isset($value['value']) ? new $cookie($value['value']) : new $cookie($value);
        }
        if (!$value instanceof $cookie) {
            throw new Exception("Error, only `{$cookie}` instances are allowed in this collection.");
        }
        return $this->_data[$name] = $value;
    }

   /**
     * Gets a set-cookie.
     *
     * @param  string $name  The cookie name.
     * @param  object $value The cookie.
     * @return object        The setted cookie.
     */
    public function offsetGet($name)
    {
        if (!array_key_exists($name, $this->_data)) {
            throw new Exception("Unexisting Cookie `'{$name}'`.");
        }
        return $this->_data[$name];
    }

    /**
     * Clones the cookies.
     */
    public function __clone()
    {
        foreach ($this->_data as $key => $value) {
            $this->_data[$key] = clone $value;
        }
    }

    /**
     * Parses a Cookie header value.
     *
     * @param  string  $header A Cookie header value.
     * @return array           An array of parsed cookies.
     */
    public static function parse($header)
    {
        $cookies = explode(';', $header);
        $data = [];
        foreach ($cookies as $cookie) {
            list($name, $value) = explode('=', $cookie);
            $name = trim($name);
            $value = urldecode($value);
            if (!isset($data[$name])) {
                $data[$name] = ['name' => $name, 'value' => [$value]];
            } else {
                $data[$name]['value'][] = $value;
            }
        }
        return array_values($data);
    }

    /**
     * Exports cookies.
     *
     * @param  Traversable $cookies The cookies.
     * @param  array       $options Options.
     * @return array                The export array.
     */
    public static function toArray($cookies, $options = [])
    {
        $data = [];
        foreach ($cookies as $name => $cookie) {
           $data[$name] = $cookie->value();
        }
        return $data;
    }

    /**
     * Builds a complete Cookie header from a cookies collection.
     *
     * @param  object $cookies A `Cookies` collection.
     * @return string
     */
    public static function toHeader($cookies)
    {
        if ($value = static::toValue($cookies)) {
            return static::NAME . ': ' . $value;
        }
    }

    /**
     * Builds a Cookie header value.
     *
     * @param  object $cookies A `Cookies` collection.
     * @return string
     */
    public static function toValue($cookies)
    {
        $result = [];
        foreach ($cookies as $name => $cookie) {
            if (!Cookie::isValidName($name)) {
                throw new Exception("Invalid cookie name `'{$name}'`.");
            }
            $parts = [];
            foreach ($cookie->data() as $value) {
                $parts[] = $name . '=' . urlencode($value);
            }
            $result[] = join('; ', $parts);
        }
        if (!$result) {
            return;
        }
        return join('; ', $result);
    }
}
