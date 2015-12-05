<?php
namespace net\http\cookie;

use Exception;
use set\Set;

/**
 * Collection of `Cookie`.
 */
class Cookies extends \collection\Collection
{
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
        'array'  => 'collection\Collection::toArray',
        'header' => 'net\http\cookie\Cookies::toCookie'
    ];

    /**
     * Constructor
     *
     * @param $config The config. Possible values are:
     *                - `'classes'`  _array_ : The class dependencies.
     */
    public function __construct($config = []) {
        $defaults = [
            'classes' => [
                'header' => 'net\http\Header',
                'cookie' => 'net\http\cookie\Cookie'
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_classes = $config['classes'];
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
            throw new Exception("Invalid cookie name `'{$name}'`.");
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
     * Parses a Cookie header string value.
     *
     * @param  string $header A single Cookie header string value.
     * @return array          The data array.
     */
    public static function parseCookie($value)
    {
        $config = [];
        list($config['name'], $value) = explode('=', $value);
        $config['value'] = urldecode($value);

        return $config;
    }

    /**
     * Builds a complete Cookie header from a cookies collection.
     *
     * @param  object $cookies A `Cookies` collection.
     * @return string
     */
    public static function toCookie($cookies)
    {
        $parts = [];
        foreach ($cookies as $name => $cookie) {
            $parts[] = static::_cookieValue($name, $cookie);
        }
        return $parts ? 'Cookie: ' . join(', ', $parts) : '';
    }

    /**
     * Builds a Cookie header value.
     *
     * @param  string $name   The cookie name.
     * @param  object $cookie The cookie instance.
     * @return string
     */
    protected static function _cookieValue($name, $cookie)
    {
        if (!Cookie::isValidName($name)) {
            throw new Exception("Invalid cookie name `'{$name}'`.");
        }
        return $name . '=' . urlencode($cookie->value());
    }
}