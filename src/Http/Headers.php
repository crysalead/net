<?php
namespace Lead\Net\Http;

use Exception;
use Lead\Net\NetException;
use Lead\Set\Set;
use Lead\Collection\Collection;

/**
 * Collection of HTTP Headers.
 */
class Headers extends \Lead\Collection\Collection
{
    /**
     * Contains cookies collection.
     *
     * @var object
     */
    public $cookies = null;

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Contains all exportable formats and their handler.
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'Lead\Collection\Collection::toArray',
        'header' => 'Lead\Net\Http\Headers::toHeader'
    ];

    /**
     * The constructor
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'data'    => [],
            'cookies' => null,
            'classes' => [
                'header' => 'Lead\Net\Http\Header'
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_classes = $config['classes'];
        $this->cookies = $config['cookies'];
        $this->push($config['data']);
    }

    /**
     * Sets some headers.
     *
     * @param  string|array $headers A header name, string content or an array of headers.
     * @param  string|array $value   The header value. I set, `$headers` must be an header string name.
     * @return self
     */
    public function push($headers, $value = '')
    {
        if (is_string($headers) && func_num_args() === 2) {
            $headers = [$headers => $value];
        }
        return $this->_set($headers);
    }

    /**
     * Prepends some headers.
     *
     * @param  string|array $headers A header name, string content or an array of headers.
     * @param  string|array $value   The header value. I set, `$headers` must be an header string name.
     * @return self
     */
    public function prepend($headers, $value = '')
    {
        if (is_string($headers) && func_num_args() === 2) {
            $headers = [$headers => $value];
        }
        return $this->_set($headers, true);
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
        return $this->push($name ?: '', $value);
    }

    /**
     * Checks whether or not an header exists.
     *
     * @param  string  $name The header name.
     * @return boolean       Returns `true` if the header exists, `false` otherwise.
     */
    public function offsetExists($name)
    {
        $name = strtolower($name);
        if (!array_key_exists($name, $this->_data)) {
            return false;
        }
        return !empty($this->_data[$name]->value());
    }

    /**
     * Returns the value of a specific header.
     *
     * @param  string $name The header name.
     * @return string       The header value.
     */
    public function offsetGet($name)
    {
        if (!isset($this->_data[strtolower($name)])) {
            $header = $this->_classes['header'];
            return $this->_data[strtolower($name)] = new $header($name, '');
        }
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
     * Returns the key of the current item.
     *
     * @return scalar Scalar on success or `null` on failure.
     */
    public function key()
    {
        if ($header = current($this->_data)) {
            return $header->name();
        }
    }

    /**
     * Returns the current item.
     *
     * @return mixed The current item or `false` on failure.
     */
    public function valid()
    {
        if (key($this->_data) === null) {
            return false;
        }
        // Bails out all empty header on forEach.
        $current = current($this->_data);
        while (!$current->value()) {
            $current = $this->next();
            if (key($this->_data) === null) {
                return false;
            }
        }
        return true;
    }

    /**
     * Sets one or some plain string headers.
     *
     * @param  string|array $headers A header string or an array of headers.
     * @param  boolean      $prepend If true, prepend headers to the beginning.
     * @return self
     */
    protected function _set($values, $prepend = false)
    {
        $header = $this->_classes['header'];
        $headers = is_string($values) ? explode("\n", $values) : $values;

        foreach ($headers as $key => $value) {
            if (!is_numeric($key)) {
                if ($value instanceof $header) {
                    $value->name($key);
                } elseif (is_array($value)) {
                    $value = "{$key}: " . join(', ', $value);
                } else {
                    $value = "{$key}: {$value}";
                }
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if (!$value) {
                continue;
            }

            if ($value instanceof $header) {
                $parsed = $value;
            } elseif (!$parsed = $header::parse($value)) {
                throw new NetException("Invalid HTTP header: `'{$value}'`.");
            }

            $name = strtolower($parsed->name());
            if (!$name) {
                throw new Exception("Error, invalid header name, can't be empty.");
            }
            if ($this->_setCookie($name, $parsed)) {
                continue;
            }
            if ($prepend) {
                $this->_data = [$name => $parsed] + $this->_data;
            } else {
                $this->_data = array_merge($this->_data, [$name => $parsed]);
            }
        }
        return $this;
    }

    /**
     * Adds a Cookie header.
     *
     * @param  string  $name    The header name.
     * @param  string  $header  The header instance.
     * @return boolean          Returns `true` if a cookie has been added, `false otherwise`.
     */
    protected function _setCookie($name, $header)
    {
        $cookies = $this->cookies;
        if (!$cookies || $name !== strtolower($cookies::NAME)) {
            return false;
        }

        foreach ($cookies::parse($header->plain()) as $cookie) {
            $cookies[$cookie['name']] = $cookie;
        }
        return true;
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
     * Clones the headers.
     */
    public function __clone()
    {
        foreach ($this->_data as $key => $value) {
            $this->_data[$key] = clone $value;
        }
        if ($this->cookies) {
            $this->cookies = clone $this->cookies;
        }
    }

    /**
     * Gets the headers as an array.
     *
     * @return array Returns the headers.
     */
    public static function toArray($collection, $options = [])
    {
        $data = [];
        foreach ($collection as $name => $header) {
            $data[$name] = $header->value();
        }
        return $data;
    }

    /**
     * Returns the headers as a string.
     *
     * @return string
     */
    public static function toHeader($headers)
    {
        $data = [];
        foreach ($headers as $key => $header) {
            $data[] = $header->to('header');
        }
        if ($headers->cookies && $result = $headers->cookies->to('header')) {
            $data[] = $result;
        }
        return $data ? join("\r\n", $data) . "\r\n\r\n" : '';
    }

}
