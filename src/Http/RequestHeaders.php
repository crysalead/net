<?php
namespace Lead\Net\Http;

use Lead\Net\NetException;
use Lead\Set\Set;
use Lead\Collection\Collection;

/**
 * Collection of HTTP Headers.
 */
class RequestHeaders extends \Lead\Net\Headers
{
    /**
     * Contains all exportable formats and their handler.
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'Lead\Collection\Collection::toArray',
        'header' => 'Lead\Net\Http\RequestHeaders::toHeader'
    ];

    /**
     * Contains cookies.
     *
     * @var object
     */
    public $_cookie = null;

    /**
     * The constructor
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'header'      => 'Lead\Net\Http\Header',
                'cookies'     => 'Lead\Net\Http\Cookie\Cookies'
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_classes = $config['classes'];
    }

    /**
     * Returns the cookie collection.
     *
     * @return object
     */
    public function cookies()
    {
        if (!$this->_cookie) {
            $cookies = $this->_classes['cookies'];
            $this->_cookie = new $cookies();
        }
        return $this->_cookie;
    }

    /**
     * Adds some headers.
     *
     * @param  string|array $headers A header string or an array of headers.
     * @param  boolean      $prepend If true, prepend headers to the beginning.
     * @return self
     */
    public function add($values, $prepend = false)
    {
        $header = $this->_classes['header'];
        $headers = is_string($values) ? explode("\n", $values) : $values;

        foreach ($headers as $key => $value) {
            if (!is_numeric($key)) {
                if (is_array($value)) {
                    $value = "{$key}: " . join(', ', $value);
                } else {
                    $value = "{$key}: {$value}";
                }
            }
            if (!$value = trim($value)) {
                continue;
            }

            if (!$parsed = $header::parse($value)) {
                throw new NetException("Invalid HTTP header: `'{$value}'`.");
            }
            $name = strtolower($parsed->name());

            if ($this->_addCookie($name, $parsed)) {
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
    protected function _addCookie($name, $header)
    {
        if ($name !== 'cookie') {
            return false;
        }
        $cookies = $this->cookies();
        foreach ($cookies::parse($header->plain()) as $cookie) {
            $cookies[$cookie['name']] = $cookie['value'];
        }
        return true;
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
        if ($cookies = $headers->cookies()->to('header')) {
            $data[] = $cookies;
        }
        return $data ? join("\r\n", $data) . "\r\n\r\n" : '';
    }
}
