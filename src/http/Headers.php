<?php
namespace net\http;

use set\Set;
use collection\Collection;

/**
 * Collection of Headers.
 */
class Headers extends \net\Headers
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'collection\Collection::toArray',
        'header' => 'net\http\Headers::toHeader'
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
                'header' => 'net\Header',
                'cookies' => 'net\http\cookie\Cookies',
                'set-cookies' => 'net\http\cookie\SetCookies'
            ]
        ];
        $config = Set::merge($defaults, $config);
        parent::__construct($config);
    }

    public function cookies()
    {
        if (!isset($this->_data['cookie'])) {
            $cookies = $this->_classes['cookies'];
            $this->_data['cookie'] = new $cookies();
        }
        return $this->_data['cookie'];

    }

    public function setCookies()
    {
        if (!isset($this->_data['set-cookie'])) {
            $setCookies = $this->_classes['set-cookies'];
            $this->_data['set-cookie'] = new $setCookies();
        }
        return $this->_data['set-cookie'];
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
                $this->_data[$name] = $parsed;
            }
        } else {
            $this->_data[(string) $value] = true;
        }
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
        return $data ? join("\r\n", $data) . "\r\n\r\n" : '';
    }
}
