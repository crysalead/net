<?php
namespace Lead\Net\Http;

use Lead\Net\NetException;
use Lead\Set\Set;

/**
 * Collection of Headers.
 */
class ResponseHeaders extends \Lead\Net\Http\RequestHeaders
{
    /**
     * The constructor
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'header'  => 'Lead\Net\Http\Header',
                'cookies' => 'Lead\Net\Http\Cookie\SetCookies'
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_classes = $config['classes'];
    }

    /**
     * Adds a Set-Cookie header.
     *
     * @param  string  $name    The header name.
     * @param  string  $header  The header instance.
     * @return boolean          Returns `true` if a cookie has been added, `false otherwise`.
     */
    protected function _addCookie($name, $header)
    {
        if ($name !== 'set-cookie') {
            return false;
        }
        $cookies = $this->cookies();
        $cookie = $cookies::parse($header->plain());
        $cookies[$cookie['name']] = $cookie;
        return true;
    }
}
