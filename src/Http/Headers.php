<?php
namespace Lead\Net\Http;

use Lead\Net\NetException;
use Lead\Set\Set;
use Lead\Collection\Collection;

/**
 * Collection of Headers.
 */
class Headers extends \Lead\Net\Headers
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
     * The constructor
     *
     * @param array $data The data
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'header'      => 'Lead\Net\Http\Header',
                'cookies'     => 'Lead\Net\Http\Cookie\Cookies',
                'set-cookies' => 'Lead\Net\Http\Cookie\SetCookies'
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
        if (!isset($this->_data['cookie'])) {
            $cookies = $this->_classes['cookies'];
            $this->_data['cookie'] = new $cookies();
        }
        return $this->_data['cookie'];

    }

    /**
     * Returns the set-cookie collection.
     *
     * @return object
     */
    public function setCookies()
    {
        if (!isset($this->_data['set-cookie'])) {
            $setCookies = $this->_classes['set-cookies'];
            $this->_data['set-cookie'] = new $setCookies();
        }
        return $this->_data['set-cookie'];
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
            $this->_add($value, $prepend);
        }
        return $this;
    }

    /**
     * Pushes an header.
     *
     * @param string  $value   The header to add.
     * @param boolean $prepend If true, prepend headers to the beginning.
     */
    protected function _add($value, $prepend)
    {
        $header = $this->_classes['header'];
        if (!$parsed = $header::parse($value)) {
            throw new NetException("Invalid HTTP header: `'{$value}'`.");
        }
        $name = strtolower($parsed->name());
        if ($name === 'cookie') {
            $cookies = $this->_classes['cookies'];
            foreach ($parsed as $cookie) {
                $cookie = $cookies::parseCookie($cookie);
                $this->cookies()[$cookie['name']] = $cookie['value'];
            }
        } elseif ($name === 'set-cookie') {
            $setCookies = $this->_classes['set-cookies'];
            foreach ($parsed as $setCookie) {
                $setCookie = $setCookies::parseSetCookie($setCookie);
                $this->setCookies()[$setCookie['name']] = $setCookie;
            }
        } else {
            if ($prepend) {
                $this->_data = [$name => $parsed] + $this->_data;
            } else {
                $this->_data = array_merge($this->_data, [$name => $parsed]);
            }
        }
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
        return $data ? join("\r\n", $data) . "\r\n\r\n" : '';
    }
}
