<?php
namespace Lead\Net\Http\Cookie;

use Exception;

/**
 * Cookie values helper
 *
 * @see http://curl.haxx.se/rfc/cookie_spec.html
 */
abstract class CookieValues
{
/**
     * Extract cookies values from a request.
     *
     * @param  string  $value A Cookie header value.
     * @return array           The cookies values.
     */
    public static function toArray($value)
    {
        $data = [];

        $cookies = $value ? explode(';', $value) : [];
        foreach ($cookies as $cookie) {
            list($name, $value) = explode('=', $cookie);
            $name = trim($name);
            $value = urldecode($value);
            if (!isset($data[$name])) {
                $data[$name] = $value;
            }
        }
        return $data;
    }

    /**
     * Format an array of key/value pairs representing cookies into a request Cookie header value.
     *
     * @param  array  $cookies Array of key/value pairs representing cookies.
     * @return string          A request Cookie header value.
     */
    public static function toString($cookies)
    {
        $data = [];
        foreach ($cookies as $name => $value) {
            $data[] = $name . '=' . $value;
        }
        return trim(join('; ', $data));
    }
}