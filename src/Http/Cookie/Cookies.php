<?php
namespace Lead\Net\Http\Cookie;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use RuntimeException;
use Lead\Set\Set;

class Cookies extends \Lead\Collection\Collection
{
    /**
     * Class dependencies
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Loaded cookie data.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Hashes.
     *
     * @var array
     */
    protected $_hashes = [];

    /**
     * Names.
     *
     * @var array
     */
    protected $_names = [];

    /**
     * Set to true to throw exceptions when invalid cookies are added to the cookie jar.
     *
     * @var boolean
     */
    protected $_strictMode = false;

    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'Lead\Net\Http\Cookie\Cookies::toArray'
    ];

    /**
     * Constructor
     *
     * @param $config The config. Possible values are:
     *                - `'strictMode'`  _array_ : Set to true to throw exceptions when invalid
     *                                            cookies are added to the cookie jar.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'strictMode' => false,
            'classes' => [
                'cookie' => 'Lead\Net\Http\Cookie\Cookie'
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_classes = $config['classes'];
        $this->_strictMode = $config['strictMode'];
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
        $value = $this->_autobox($name, $value);
        if (!$value instanceof $cookie) {
            throw new RuntimeException("Error, only `{$cookie}` instances are allowed in this collection.");
        }
        $hash = $name . ';' . $value->domain() . ';' . $value->path();
        $this->_hashes[$name][] = $hash;
        $this->_names[$hash] = $name;

        return $this->_data[$hash] = $value;
    }


    /**
     * Gets a set-cookie.
     *
     * @param  string $name  The cookie name.
     * @param  object $value The cookie.
     * @return object        The setted cookie.
     */
    public function offsetGet($name)
    {
        $data = [];
        if (!isset($this->_hashes[$name])) {
            throw new RuntimeException("Unexisting Set-Cookie `'{$name}'`.");
        }
        foreach ($this->_hashes[$name] as $key => $hash) {
            $data[] = $this->_data[$hash];
        }
        return $data;
    }

    /**
     * Checks if a set-cookie of a specific name exists.
     *
     * @param  string  $name The cookie name.
     * @return boolean
     */
    public function offsetExists($name)
    {
        return isset($this->_hashes[$name]);
    }

    /**
     * Removes all set-cookies of a specific name.
     *
     * @param string $name The cookie name.
     */
    public function offsetUnset($name)
    {
        if (!isset($this->_hashes[$name])) {
            return;
        }
        foreach ($this->_hashes[$name] as $hash) {
            unset($this->_data[$hash]);
            unset($this->_names[$hash]);
        }
        unset($this->_hashes[$name]);
    }

    /**
     * Returns the key of the current item.
     *
     * @return scalar Scalar on success or `null` on failure.
     */
    public function key()
    {
        $hash = key($this->_data);
        return $this->_names[$hash];
    }

    /**
     * Returns the item keys.
     *
     * @return array The keys of the items.
     */
    public function keys()
    {
        return array_keys($this->_hashes);
    }

    /**
     * Autoboxes a cookie value.
     *
     * @param  mixed  $value The cookie value.
     * @return object        The cookie instance.
     */
    protected function _autobox($name, $value)
    {
        if (is_object($value)) {
            return $value;
        }
        $cookie = $this->_classes['cookie'];
        if (!is_array($value)) {
            $value = ['value' => $value];
        }
        $value['name'] = $name;
        return new $cookie($value);
    }

    /**
     * Removes expired cookies.
     *
     * @return object Returns `$this`.
     */
    public function flushExpired()
    {
        foreach ($this->_hashes as $name => $hashes) {
            foreach ($hashes as $key => $hash) {
                $cookie = $this->_data[$hash];
                if ($cookie->expired()) {
                    unset($this->_data[$hash]);
                    unset($this->_names[$hash]);
                    unset($this->_hashes[$name][$key]);
                    if (!$this->_hashes[$name]) {
                        unset($this->_hashes[$name]);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Remove some cookies from the Jar.
     *
     * @param  string  $domain A domain name.
     * @param  string  $path   A path name.
     * @param  string  $name   A cookie name.
     * @return self
     */
    public function clear($domain = null, $path = null, $name = null)
    {
        if (!$domain) {
            $this->_data = [];
            return;
        } elseif (!$path) {
            $this->_data = array_filter(
                $this->_data,
                function ($cookie) use ($path, $domain) {
                    return !$cookie->matchesDomain($domain);
                }
            );
        } elseif (!$name) {
            $this->_data = array_filter(
                $this->_data,
                function ($cookie) use ($path, $domain) {
                    return !($cookie->matchesPath($path) &&
                        $cookie->matchesDomain($domain));
                }
            );
        } else {
            $this->_data = array_filter(
                $this->_data,
                function ($cookie) use ($path, $domain, $name) {
                    return !($cookie->name() == $name &&
                        $cookie->matchesPath($path) &&
                        $cookie->matchesDomain($domain));
                }
            );
        }
        return $this;
    }

    /**
     * Clear session based cookies.
     *
     * @return self
     */
    public function clearSessionCookies()
    {
        $this->_data = array_filter(
            $this->_data,
            function ($cookie) {
                return !$cookie->discard() && $cookie->expires();
            }
        );
        return $this;
    }

    /**
     * Add a cookie.
     *
     * @param  SetCookie $cookie The cookie to add.
     * @return boolean
     */
    public function add($cookie)
    {
        // If the name string is empty (but not 0), ignore the set-cookie
        // string entirely.
        $name = $cookie->name();

        if (!$name && $name !== '0') {
            return false;
        }

        // Only allow cookies with set and valid domain, name, value
        $result = $cookie->isValid();
        if ($result !== true) {
            if ($this->_strictMode) {
                throw new RuntimeException('Invalid cookie: ' . $result);
            } else {
                $cookieValue = $cookie->value();
                if ($cookieValue === null || $cookieValue === '') {
                    $this->clear($cookie->domain(), $cookie->path(), $cookie->name());
                }
                return false;
            }
        }

        // Resolve conflicts with previously set cookies
        foreach ($this->_data as $i => $c) {

            // Two cookies are identical, when their path, and domain are
            // identical.
            if ($c->path() != $cookie->path() ||
                $c->domain() != $cookie->domain() ||
                $c->name() != $cookie->name()
            ) {
                continue;
            }

            // The previously set cookie is a discard cookie and this one is
            // not so allow the new cookie to be set
            if (!$cookie->discard() && $c->discard()) {
                unset($this->_data[$i]);
                continue;
            }

            // If the new cookie's expiration is further into the future, then
            // replace the old cookie
            if ($cookie->expires() > $c->expires()) {
                unset($this->_data[$i]);
                continue;
            }

            // If the value has changed, we better change it
            if ($cookie->value() !== $c->value()) {
                unset($this->_data[$i]);
                continue;
            }

            // The cookie exists, so no need to continue
            return false;
        }

        $this->_data[] = $cookie;

        return true;
    }

    /**
     * Collect the Set-Cookie header from a response.
     *
     * @param  RequestInterface  $request  The request.
     * @param  ResponseInterface $response The response.
     * @return self
     */
    public function fetchCookies($request, $response)
    {
        foreach ($response->cookies($request) as $cookie) {
            $this->add($cookie);
        }
        return $this;
    }

    /**
     * Computes cookie path following RFC 6265 section 5.1.4
     *
     * @link https://tools.ietf.org/html/rfc6265#section-5.1.4
     *
     * @param RequestInterface $request
     * @return string
     */
    protected function _pathFrom(RequestInterface $request)
    {
        $uriPath = $request->path();
        if ($uriPath === '') {
            return '/';
        }
        if (strpos($uriPath, '/') !== 0) {
            return '/';
        }
        if ($uriPath === '/') {
            return '/';
        }
        if (($lastSlashPos = strrpos($uriPath, '/')) === 0) {
            return '/';
        }

        return substr($uriPath, 0, $lastSlashPos);
    }

    /**
     * Clones the cookies.
     */
    public function __clone()
    {
        foreach ($this->_data as $key => $value) {
            $this->_data[$key] = clone $value;
        }
    }

    /**
     * Exports set-cookies.
     *
     * @param  Traversable $setCookies The set-cookies.
     * @param  array       $options    Options.
     * @return array                   The export array.
     */
    public static function toArray($cookies, $options = [])
    {
        $data = [];
        foreach ($cookies as $name => $cookie) {
            $data[$name][] = $cookie->data();
        }
        return $data;
    }
}
