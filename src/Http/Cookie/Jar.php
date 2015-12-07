<?php
namespace Lead\Net\Http\Cookie;

use Exception;

/**
 * Jar parser
 */
class Jar
{
    /**
     * Exports the cookies into a JAR string.
     *
     * @param  object $cookies A `SetCookies` collection.
     * @return string
     */
    public static function toJar($cookies)
    {
        $result = [];
        foreach ($cookies as $name => $cookie) {
            if (!$cookie->expired()) {
                $result[] = static::_line($name, $cookie);
            }
        }
        return $result ? join("\n", $result) . "\n" : '';
    }

    /**
     * Creates a cookie JAR line from a name and a SetCookie value.
     *
     * @param  string The cookie name.
     * @param  string The cookie instance value.
     * @return string
     */
    protected static function _line($name, $cookie)
    {
        if (!$cookie::isValidName($name)) {
            throw new Exception("Invalid cookie name `'{$name}'`.");
        }
        $domain =  $cookie->domain();

        $parts = [
            $cookie->httponly() ? '#HttpOnly_' . $domain : $domain,
            $domain === '.' ? 'TRUE' : 'FALSE',
            $cookie->path() ?: '/',
            $cookie->secure() ? 'TRUE' : 'FALSE',
            (string) $cookie->expires(),
            $name,
            $cookie->value()
        ];
        return join("\t", $parts);
    }

    /**
     * Parses a cookie JAR line.
     *
     * @param  string    $line The cookie JAR line.
     * @return array           The data array.
     * @throws Exception       It throws an exception the cookie JAR line is invalid.
     */
    public static function parse($line)
    {
        $parts = explode("\t", trim($line));

        if (count($parts) !== 7) {
            throw new Exception("Invalid cookie JAR format.");
        }

        $config = [];
        $config['httponly'] = '#HttpOnly_' === substr($parts[0], 0, 10);
        $config['domain'] = $config['httponly'] ? substr($parts[0], 10) : $parts[0];
        $config['path'] = $parts[2];
        $config['secure'] = ($parts[3] === 'TRUE') ? true : false;
        $config['expires'] = (integer) $parts[4];
        $config['name'] = $parts[5];
        $config['value'] = $parts[6];

        return $config;
    }

}