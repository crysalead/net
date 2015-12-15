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
    /**
     * Class dependencies
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Hashes to names map.
     *
     * @var array
     */
    protected $_names = [];

    /**
     * Map to hashes map.
     *
     * @var array
     */
    protected $_hashes = [];

    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'Lead\Net\Http\cookie\Cookies::toArray',
        'header' => 'Lead\Net\Http\cookie\Cookies::toHeader'
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
                'header' => 'Lead\Net\Http\Header',
                'cookie' => 'Lead\Net\Http\Cookie\Cookie'
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

        $hash = spl_object_hash($value);
        $this->_hashes[$name][] = $hash;
        $this->_names[$hash] = $name;

        return $this->_data[$hash] = $value;
    }

    /**
     * Checks if a cookie of a specific name exists.
     *
     * @param  string  $name The cookie name.
     * @return boolean
     */
    public function offsetExists($name)
    {
        return isset($this->_hashes[$name]);
    }

    /**
     * Removes all cookies of a specific name.
     *
     * @param string $name The cookie name.
     */
    public function offsetUnset($name)
    {
        if (!isset($this->_hashes[$name])) {
            return;
        }
        foreach ($this->_hashes[$name] as $hash) {
            unset($this->_data[$hash]);
            unset($this->_names[$hash]);
        }
        unset($this->_hashes[$name]);
    }

    /**
     * Gets a cookie.
     *
     * @see http://tools.ietf.org/html/rfc6265, section 5.4.2
     * @see Cookies with longer paths are listed before cookies with shorter paths.
     *
     * @param  string $name  The cookie name.
     * @return object        The first cookie occurrence matching the required name.
     */
    public function offsetGet($name)
    {
        if (!isset($this->_hashes[$name])) {
            throw new Exception("Unexisting cookie `'{$name}'`.");
        }
        return $this->_data[reset($this->_hashes[$name])];
    }

    /**
     * Returns the key of the current item.
     *
     * @return scalar Scalar on success or `null` on failure.
     */
    public function key()
    {
        $hash = key($this->_data);
        return $this->_names[$hash];
    }

    /**
     * Returns the item keys.
     *
     * @return array The keys of the items.
     */
    public function keys()
    {
        return array_keys($this->_hashes);
    }

    /**
     * Parses a Cookie header value.
     *
     * @param  string    $header A Cookie header value.
     * @return Generator         A generator.
     */
    public static function parse($value)
    {
        $cookies = explode(';', $value);
        foreach ($cookies as $cookie) {
            list($name, $value) = explode('=', $cookie);
            $name = trim($name);
            $value = urldecode($value);
            yield compact('name', 'value');
        }
    }

    /**
     * Builds a complete Cookie header from a cookies collection.
     *
     * @param  object $cookies A `Cookies` collection.
     * @return string
     */
    public static function toHeader($cookies)
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
}
