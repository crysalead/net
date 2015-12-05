<?php
namespace net\http\cookie;

use Exception;
use set\Set;

/**
 * Collection of `SetCookie`.
 */
class SetCookies extends \collection\Collection
{
    /**
     * Class dependencies
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'collection\Collection::toArray',
        'header' => 'net\http\cookie\SetCookies::toSetCookie'
    ];

    /**
     * Default values to use for newly created SetCookie instance
     *
     * @var array
     */
    protected $_scope = [];

    /**
     * Constructor
     *
     * @param $config The config. Possible values are:
     *                - `'defaults'` _array_ : Default config options to pass to newly created cookie instances.
     *                - `'classes'`  _array_ : The class dependencies.
     */
    public function __construct($config = []) {
        $defaults = [
            'secure'  => false,
            'domain'  => null,
            'path'    => '/',
            'classes' => [
                'header' => 'net\http\Header',
                'cookie' => 'net\http\cookie\SetCookie'
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_classes = $config['classes'];
        unset($config['classes']);
        $this->_scope = $config;
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
        $value = $this->_autobox($value);
        if (!$value instanceof $cookie) {
            throw new Exception("Error, only `{$cookie}` instances are allowed in this collection.");
        }
        if (!$this->_match($value)) {
            throw new Exception("Error, the cookie's scope doesn't match the collection's one.");
        }
        return $this->_data[$name] = $value;
    }

    /**
     * Autoboxes a cookie value.
     *
     * @param  mixed  $value The cookie value.
     * @return object        The cookie instance.
     */
    protected function _autobox($value) {
        if (is_object($value)) {
            return $value;
        }
        $cookie = $this->_classes['cookie'];
        if (!is_array($value)) {
            return new $cookie($value, $this->_scope);
        }
        return new $cookie(isset($value['value']) ? $value['value'] : '', $value + $this->_scope);
    }

    /**
     * Checks if a cookie is compatible with the `SetCookies` scope.
     *
     * @param  object  $value The cookie instance.
     * @return boolean        Returns `true` if match, `false` otherwise.
     */
    protected function _match($cookie) {
        return (
            $this->_scope['secure'] === $cookie->secure() &&
            $this->_scope['domain'] === $cookie->domain() &&
            $this->_scope['path'] === $cookie->path()
        );
    }

    /**
     * Removes expired cookies.
     *
     * @return object Returns `$this`.
     */
    public function flushExpired()
    {
        foreach ($this->_data as $name => $cookie) {
            if ($cookie->expired()) {
                unset($this->_data[$name]);
            }
        }
        return $this;
    }

    /**
     * Parses Set-Cookie header string value.
     *
     * @param  string $value The Set-Cookie header string value.
     * @param  string $url   The URL of the response.
     * @return array         The cookie data array.
     */
    public static function parseSetCookie($value, $url = null)
    {
        $config = [];

        $infos = parse_url($url);

        $config['secure'] = isset($infos['scheme']) && $infos['scheme'] === 'https';
        $config['domain'] = isset($infos['host']) ? $infos['host'] : null;

        if (isset($infos['path'])) {
            $config['path'] = substr($infos['path'], 0, strrpos($infos['path'], '/') + 1);
        }

        $parts = explode(';', $value);

        list($config['name'], $value) = explode('=', array_shift($parts));
        $config['value'] = urldecode($value);

        foreach ($parts as $item) {
            $parts = explode('=', trim($item));
            $parts[0] = strtolower($parts[0]);

            switch ($parts[0]) {
                case 'expires':
                case 'max-age':
                case 'path':
                    $config[$parts[0]] = $parts[1];
                break;
                case 'domain':
                    $config['domain'] = $parts[1][0] === '.' ? $parts[1] : ".{$parts[1]}";
                break;
                case 'secure':
                case 'httponly':
                    $config[$parts[0]] = true;
                break;
            }
        }
        return $config;
    }

    /**
     * Builds a complete Set-Cookie header from a cookies collection.
     *
     * @param  object $cookies A `SetCookies` collection.
     * @return string
     */
    public static function toSetCookie($cookies)
    {
        $parts = [];
        foreach ($cookies as $name => $cookie) {
            if (!$cookie->expired()) {
                $parts[] = static::_setCookieValue($name, $cookie);
            }
        }
        return $parts ? 'Set-Cookie: ' . join("\r\nSet-Cookie: ", $parts) : '';
    }

    /**
     * Builds a Set-Cookie header value.
     *
     * @param  string $name   The cookie name.
     * @param  object $cookie The cookie instance.
     * @return string
     */
    protected static function _setCookieValue($name, $cookie)
    {
        if (!Cookie::isValidName($name)) {
            throw new Exception("Invalid cookie name `'{$name}'`.");
        }

        $data = $cookie->data();

        $parts = [];
        $parts[] = $name . '=' . urlencode($data['value']);

        if (isset($data['max-age'])) {
            $parts[] = 'Max-Age=' . (string) $data['max-age'];
        } elseif (isset($data['expires'])) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $data['expires']);
        }
        if ($data['path']) {
            $parts[] = 'Path=' . $data['path'];
        }
        if ($data['domain']) {
            $parts[] = 'Domain=' . $data['domain'];
        }
        if ($data['secure']) {
            $parts[] = 'Secure';
        }
        if ($data['httponly']) {
            $parts[] = 'HttpOnly';
        }
        return join('; ', $parts);
    }
}