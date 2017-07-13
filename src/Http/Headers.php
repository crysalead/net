<?php
namespace Lead\Net\Http;

use Exception;
use Lead\Net\NetException;
use Lead\Set\Set;
use Lead\Collection\Collection;
use Lead\Net\Behavior\HeadersTrait;

/**
 * Collection of HTTP Headers.
 */
class Headers extends \Lead\Collection\Collection
{
    use HeadersTrait;

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
        'array'  => 'Lead\Net\Http\Headers::toArray',
        'list'   => 'Lead\Net\Http\Headers::toList',
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
                throw new NetException("Invalid header: `'{$value}'`.");
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
    public static function toArray($headers, $options = [])
    {
        $data = [];
        foreach ($headers as $name => $header) {
            $data[$name] = $header->value();
        }
        if ($headers->cookies && $result = $headers->cookies->to('value')) {
            $cookies = $headers->cookies;
            $data[$cookies::NAME] = $result;
        }
        return $data;
    }

    /**
     * Gets the headers as an array.
     *
     * @return array Returns the headers.
     */
    public static function toList($headers, $options = [])
    {
        $data = [];
        foreach ($headers as $name => $header) {
            $data[] = $name . ': ' . $header->value();
        }
        if ($headers->cookies && $result = $headers->cookies->to('header')) {
            $data[] = $result;
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
