<?php
namespace Lead\Net\Http\Cookie;

use Exception;

/**
 * SetCookie value definition.
 *
 * @see http://curl.haxx.se/rfc/cookie_spec.html
 */
class SetCookie extends \Lead\Net\Http\Cookie\Cookie
{
    /**
     * SetCookie data.
     *
     * @var string
     */
    protected $_data = [
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
     * @param array $config The SetCookie data.
     */
    public function __construct($value, $config = [])
    {
        $defaults = [
            'expires'  => null,
            'path'     => '/',
            'domain'   => null,
            'max-age'  => null,
            'secure'   => false,
            'httponly' => false
        ];
        $config += $defaults;

        $this->value($value);
        $this->expires($config['expires']);
        $this->path($config['path']);
        $this->domain($config['domain']);
        $this->maxAge($config['max-age']);
        $this->secure($config['secure']);
        $this->httponly($config['httponly']);
    }

    /**
     * Gets/sets the SetCookie Domain.
     *
     * @param  string $domain The domain value.
     * @return string         Returns the domain value.
     */
    public function domain($domain = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['domain'];
        }
        if ($domain !== null && substr_count($domain, '.') < 2) {
            throw new Exception("Invalid Domain `'{$domain}'`, at least two `'.'` are required.");
        }
        return $this->_data['domain'] = $domain === null ?  $domain : (string) $domain;
    }

    /**
     * Gets/sets the SetCookie Path.
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
     * Gets/sets the SetCookie Max-Age.
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
     * Gets/Sets the SetCookie Expires.
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
     * Gets/sets the SetCookie Secure.
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
     * Checks if a scheme, domain and path match the SetCookie ones.
     *
     * @param  string  $url The URL path.
     * @return boolean      Returns `true` if it matches, `false otherwise`.
     */
    public function match($url)
    {
        $infos = parse_url($url);

        if (!$this->_matchScheme($infos['scheme'])) {
            return false;
        }

        if (!$this->_matchDomain($infos['host'])) {
            return false;
        }

        return $this->_data['path'] && strpos($infos['path'], $this->_data['path']) === 0;
    }

    /**
     * Checks if a domain match the scheme.
     *
     * @param  string  $scheme The scheme name.
     * @return boolean         Returns `true` if schemes match, `false otherwise`.
     */
    protected function _matchScheme($scheme) {
        $scheme = strtolower($scheme);
        $secure = $this->_data['secure'];
        return ($secure && $scheme === 'https') || (!$secure && $scheme === 'http');
    }

    /**
     * Checks if a domain match the SetCookie domain.
     *
     * @param  string  $domain The domain name.
     * @return boolean         Returns `true` if domains match, `false otherwise`.
     */
    protected function _matchDomain($domain)
    {
        $hostDomain = strtolower($domain);
        $cookieDomain = strtolower($this->_data['domain']);

        if (!$cookieDomain) {
            return false;
        }

        if ($cookieDomain[0] === '.') {
            $cookieDomain = substr($cookieDomain, 1);
        }

        return ($cookieDomain == $hostDomain || preg_match('/\.' . preg_quote($cookieDomain) . '$/', $hostDomain));
    }

    /**
     * Checks if the SetCookie expired.
     *
     * @param  boolean $onSessionExpires Sets to `true` to check if the SetCookie will expires when the session expires.
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
