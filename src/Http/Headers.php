<?php
namespace Lead\Net\Http;

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
     * HTTP Status.
     *
     * @var string
     */
    protected $_status = null;

    /**
     * The constructor
     *
     * @param array $data The data
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'header'      => 'Lead\Net\Header',
                'cookies'     => 'Lead\Net\Http\Cookie\Cookies',
                'set-cookies' => 'Lead\Net\Http\Cookie\SetCookies'
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
            if (preg_match('/HTTP\/(\d+\.\d+)\s+(\d+)(?:\s+(.*))?/i', $value, $matches)) {
                $this->status($value);
            } else {
                $this->_data[(string) $value] = true;
            }
        }
    }


    /**
     * Sets/gets the status for the response.
     *
     * @param  string $status The HTTP status.
     * @return string         Returns the full HTTP status.
     */
    public function status($status = null)
    {
        if (func_num_args() === 0) {
            return $this->_status;
        }
        $this->_status = $status;
        return $this;
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
            if ($header === true) {
                $data[] = $key;
            } elseif ($header = $header->to('header')) {
                $data[] = $header;
            }
        }
        if ($status = $headers->status()) {
            array_unshift($data, $status);
        }
        return $data ? join("\r\n", $data) . "\r\n\r\n" : '';
    }
}
