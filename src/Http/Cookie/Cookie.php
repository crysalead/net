<?php
namespace Lead\Net\Http\Cookie;

use Exception;

/**
 * Cookie value definition.
 *
 * @see http://curl.haxx.se/rfc/cookie_spec.html
 */
class Cookie
{
    /**
     * Cookie data.
     *
     * @var string
     */
    protected $_data = [
        'name'     => '',
        'value'    => '',
        'expires'  => null,
        'path'     => '/',
        'domain'   => null,
        'max-age'  => null,
        'secure'   => false,
        'httponly' => false
    ];

    /**
     * Constructor.
     *
     * @param array $config The Cookie data.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'name'     => '',
            'value'    => '',
            'expires'  => null,
            'path'     => '/',
            'domain'   => null,
            'max-age'  => null,
            'secure'   => false,
            'httponly' => false
        ];
        if (is_array($config)) {
            $config = array_change_key_case($config, CASE_LOWER);
            $config += $defaults;
        } else {
            $config = ['value' => $config] + $defaults;
        }
        $this->name($config['name']);
        $this->value($config['value']);
        $this->expires($config['expires']);
        $this->path($config['path']);
        $this->domain($config['domain']);
        $this->maxAge($config['max-age']);
        $this->secure($config['secure']);
        $this->httponly($config['httponly']);
    }

    /**
     * Gets/sets the Cookie Name.
     *
     * @param  string $name A name.
     * @return string       Returns the name.
     */
    public function name($name = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['name'];
        }
        $name = (string) $name;
        return $this->_data['name'] = $name;
    }

    /**
     * Gets/sets the Cookie Value.
     *
     * @param  string $value The value.
     * @return string        Returns the value.
     */
    public function value($value = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['value'];
        }
        $value = (string) $value;
        return $this->_data['value'] = $value;
    }

    /**
     * Gets/sets the Cookie Domain.
     *
     * @param  string $domain The domain value.
     * @return string         Returns the domain value.
     */
    public function domain($domain = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['domain'];
        }
        return $this->_data['domain'] = $domain === null ?  $domain : (string) $domain;
    }

    /**
     * Gets/sets the Cookie Path.
     *
     * Note: in the following example the two cookies are distinct cookies according to RFC6265.
     *
     * ```
     * Set-Cookie: n1=v1; Path=/abc
     * Set-Cookie: n2=v2; Path=/abc/
     * ```
     *
     * so for a request path equal to "/abc", the second cookie won't be send.
     *
     * @param  string $path The path value.
     * @return string       Returns the path value.
     */
    public function path($path = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['path'];
        }
        if ($path !== null && $path[0] !== '/') {
            throw new Exception("Invalid Path `'{$path}'`, non empty and leading slash are required.");
        }
        return $this->_data['path'] = $path === null ?  $path : (string) $path;
    }

    /**
     * Gets/sets the Cookie Max-Age.
     *
     * @param  integer $maxAge The Max-Age value.
     * @return integer         Returns the Max-Age value.
     */
    public function maxAge($maxAge = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['max-age'];
        }
        return $this->_data['max-age'] = $maxAge === null ?  $maxAge : (integer) $maxAge;
    }

    /**
     * Gets/Sets the Cookie Expires.
     *
     * @param  mixed   $expires The expiration time.
     * @return integer         Returns expiration timestamp.
     */
    public function expires($expires = null)
    {
        if (func_num_args() === 0) {
            return (integer) $this->_data['expires'];
        }
        if ($expires === null) {
            return $this->_data['expires'] = null;
        }
        if (static::isValidTimeStamp($expires)) {
            return $this->_data['expires'] = $expires;
        }

        $this->_data['expires'] = strtotime($expires);
        return $this->_data['expires'] = (integer) $this->_data['expires'];
    }

    /**
     * Gets/sets the Cookie Secure.
     *
     * @param  boolean $secure The secure value.
     * @return boolean         Returns the secure value.
     */
    public function secure($secure = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['secure'];
        }
        return $this->_data['secure'] = (boolean) $secure;
    }

    /**
     * Gets/sets the coookie HttpOnly.
     *
     * @param  boolean $httpOnly The HTTP only value.
     * @return boolean           Returns the HTTP only value.
     */
    public function httpOnly($httpOnly = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['httponly'];
        }
        return $this->_data['httponly'] = (boolean) $httpOnly;
    }

    /**
     * Set whether or not this is a session cookie.
     *
     * @param  boolean $discard The discard value.
     * @return boolean          Returns discard value.
     */
    public function discard($discard = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['discard'];
        }
        return $this->_data['discard'] = (boolean) $discard;
    }

    /**
     * Returns the Cookie data array.
     *
     * @return array
     */
    public function data()
    {
        return $this->_data;
    }

    /**
     * Checks if a scheme, domain and path match the Cookie ones.
     *
     * @param  string  $url The URL path.
     * @return boolean      Returns `true` if it matches, `false otherwise`.
     */
    public function matches($url)
    {
        $infos = parse_url($url);

        if ($this->expired()) {
            return false;
        }

        if (!$this->matchesScheme($infos['scheme'])) {
            return false;
        }

        if (!$this->matchesDomain($infos['host'])) {
            return false;
        }

        return $this->matchesPath($infos['path']);
    }

    /**
     * Checks if a path match the Cookie path.
     *
     * @param  string  $scheme The scheme name.
     * @return boolean         Returns `true` if schemes match, `false otherwise`.
     */
    public function matchesPath($path)
    {
        if ($this->_data['path'] === '/' || $this->_data['path'] === $path) {
            return true;
        }

        if (strpos($path, $this->_data['path']) !== 0) {
            return false;
        }

        if (substr($this->_data['path'], -1, 1) === '/') {
            return true;
        }

        return substr($path, strlen($this->_data['path']), 1) === '/';
    }

    /**
     * Checks if a domain match the Cookie scheme.
     *
     * @param  string  $scheme The scheme name.
     * @return boolean         Returns `true` if schemes match, `false otherwise`.
     */
    public function matchesScheme($scheme)
    {
        $scheme = strtolower($scheme);
        $secure = $this->_data['secure'];
        return ($secure && $scheme === 'https') || (!$secure && $scheme === 'http');
    }

    /**
     * Checks if a domain match the Cookie domain.
     * http://tools.ietf.org/html/rfc6265#section-5.2.3
     *
     * @param  string  $domain The domain name.
     * @return boolean         Returns `true` if domains match, `false otherwise`.
     */
    public function matchesDomain($domain)
    {
        if (!$this->_data['domain']) {
            return false;
        }

        $hostDomain = strtolower($domain);
        $cookieDomain = ltrim(strtolower($this->_data['domain']), '.');

        if (!$cookieDomain || !strcmp($domain, $cookieDomain)) {
            return true;
        }

        if (filter_var($hostDomain, FILTER_VALIDATE_IP)) {
            return false;
        }

        return (bool) preg_match('~\.' . preg_quote($cookieDomain) . '$~', $hostDomain);
    }

    /**
     * Checks if the Cookie expired.
     *
     * @param  boolean $onSessionExpires Sets to `true` to check if the Cookie will expires when the session expires.
     * @return boolean
     */
    public function expired($onSessionExpires = false)
    {
        if (!$this->_data['expires'] && $onSessionExpires) {
            return true;
        }

        return $this->_data['expires'] < time() && $this->_data['expires'];
    }

    /**
     * Check if the cookie is valid according to RFC 6265
     *
     * @return bool|string Returns true if valid or an error message if invalid
     */
    public function isValid()
    {
        // Names must not be empty, but can be 0
        $name = $this->name();
        if (empty($name) && !is_numeric($name)) {
            return 'The cookie name must not be empty';
        }
        // Check if any of the invalid characters are present in the cookie name
        if (preg_match('/[\x00-\x20\x22\x28-\x29\x2c\x2f\x3a-\x40\x5c\x7b\x7d\x7f]/', $name)) {
            return 'Cookie name must not contain invalid characters: ASCII Control characters (0-31;127), space, tab and the following characters: ()<>@,;:\"/?={}';
        }
        // Value must not be empty, but can be 0
        $value = $this->value();
        if (empty($value) && !is_numeric($value)) {
            return 'The cookie value must not be empty';
        }
        // Domains must not be empty, but can be 0
        // A "0" is not a valid internet domain, but may be used as server name
        // in a private network.
        $domain = $this->domain();
        if (empty($domain) && !is_numeric($domain)) {
            return 'The cookie domain must not be empty';
        }
        return true;
    }

    /**
     * Return a Set-Cookie string representation of a Cookie.
     *
     * @param  string $cookie The Cookie.
     * @return string
     */
    public function toString()
    {
        $parts = [];
        $data = $this->data();
        $parts[] = $data['name'] . '=' . rawurlencode($data['value']);

        if ($data['domain']) {
            $parts[] = 'Domain=' . $data['domain'];
        }
        if ($data['path']) {
            $parts[] = 'Path=' . $data['path'];
        }
        if (isset($data['max-age'])) {
            $parts[] = 'Max-Age=' . (string) $data['max-age'];
        } elseif (isset($data['expires'])) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $data['expires']);
        }
        if ($data['secure']) {
            $parts[] = 'Secure';
        }
        if ($data['httponly']) {
            $parts[] = 'HttpOnly';
        }
        return join('; ', $parts);
    }

    /**
     * Create a new Cookie object from a Set-Cookie header value
     *
     * @param string $value Set-Cookie header value.
     *
     * @return self
     */
    public static function fromString($value)
    {
        $parts = array_filter(array_map('trim', explode(';', $value)));

        if (empty($parts) || !strpos($parts[0], '=')) {
            return [];
        }
        $config = [];

        $pieces = explode('=', array_shift($parts), 2);

        if (count($pieces) !== 2) {
            return [];
        }
        $config['name'] = trim($pieces[0]);
        $config['value'] = urldecode(trim($pieces[1], " \n\r\t\0\x0B"));

        foreach ($parts as $item) {
            $pieces = explode('=', trim($item), 2);
            $pieces[0] = strtolower($pieces[0]);
            if (count($pieces) !== 2) {
                $config[$pieces[0]] = true;
            } else {
                $config[$pieces[0]] = $pieces[1];
            }
        }
        return new static($config);
    }

    /**
     * Evaluate if this cookie should be persisted to storage that survives between requests.
     *
     * @param  Cookie  $cookie              The cookie being evaluated.
     * @param  boolean $allowSessionCookies If we should persist session cookies
     * @return boolean
     */
    public static function shouldPersist($cookie, $allowSessionCookies = false)
    {
        if ($cookie->expires() || $allowSessionCookies) {
            if (!$cookie->discard()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a timestamp is valid.
     *
     * @param  integer|string $timestamp The timestamp to check.
     * @return boolean
     */
    public static function isValidTimeStamp($timestamp)
    {
        return ((string) (integer) $timestamp === (string) $timestamp) && $timestamp <= PHP_INT_MAX && $timestamp >= ~PHP_INT_MAX;
    }
}