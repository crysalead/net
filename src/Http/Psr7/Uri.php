<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */
namespace Lead\Net\Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

/**
 * Implementation of Psr\Http\UriInterface.
 *
 * Provides a value object representing a URI for HTTP requests.
 *
 * Instances of this class  are considered immutable; all methods that
 * might change state are implemented such that they retain the internal
 * state of the current instance and return a new instance that contains the
 * changed state.
 */
class Uri implements UriInterface
{
    /**
     * Sub-delimiters used in query strings and fragments.
     *
     * @const string
     */
    const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    /**
     * Unreserved characters used in paths, query strings, and fragments.
     *
     * @const string
     */
    const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    /**
     * @var int[] Array indexed by valid scheme names to their corresponding ports.
     */
    protected $_allowedSchemes = [
        'http'  => 80,
        'https' => 443,
    ];

    /**
     * @var string
     */
    protected $_scheme = '';

    /**
     * @var string
     */
    protected $_userInfo = '';

    /**
     * @var string
     */
    protected $_host = '';

    /**
     * @var int
     */
    protected $_port;

    /**
     * @var string
     */
    protected $_path = '';

    /**
     * @var string
     */
    protected $_query = '';

    /**
     * @var string
     */
    protected $_fragment = '';

    /**
     * generated uri string cache
     * @var string|null
     */
    protected $_uriString;

    /**
     * @param string $uri
     * @throws InvalidArgumentException on non-string $uri argument
     */
    public function __construct($uri = '')
    {
        if (! is_string($uri)) {
            throw new InvalidArgumentException(sprintf(
                'URI passed to constructor must be a string; received "%s"',
                (is_object($uri) ? get_class($uri) : gettype($uri))
            ));
        }

        if (! empty($uri)) {
            $this->_parseUri($uri);
        }
    }

    /**
     * Operations to perform on clone.
     *
     * Since cloning usually is for purposes of mutation, we reset the
     * $uriString property so it will be re-calculated.
     */
    public function __clone()
    {
        $this->_uriString = null;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        if (null !== $this->_uriString) {
            return $this->_uriString;
        }

        $this->_uriString = static::_createUriString(
            $this->_scheme,
            $this->getAuthority(),
            $this->getPath(), // Absolute URIs should use a "/" for an empty path
            $this->_query,
            $this->_fragment
        );

        return $this->_uriString;
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme()
    {
        return $this->_scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthority()
    {
        if (empty($this->_host)) {
            return '';
        }

        $authority = $this->_host;
        if (! empty($this->_userInfo)) {
            $authority = $this->_userInfo . '@' . $authority;
        }

        if ($this->_isNonStandardPort($this->_scheme, $this->_host, $this->_port)) {
            $authority .= ':' . $this->_port;
        }

        return $authority;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInfo()
    {
        return $this->_userInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function getHost()
    {
        return $this->_host;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort()
    {
        return $this->_isNonStandardPort($this->_scheme, $this->_host, $this->_port)
            ? $this->_port
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        return $this->_query;
    }

    /**
     * {@inheritdoc}
     */
    public function getFragment()
    {
        return $this->_fragment;
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme($scheme)
    {
        if (! is_string($scheme)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a string argument; received %s',
                __METHOD__,
                (is_object($scheme) ? get_class($scheme) : gettype($scheme))
            ));
        }

        $scheme = $this->_filterScheme($scheme);

        if ($scheme === $this->_scheme) {
            // Do nothing if no change was made.
            return clone $this;
        }

        $new = clone $this;
        $new->_scheme = $scheme;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withUserInfo($user, $password = null)
    {
        if (! is_string($user)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a string user argument; received %s',
                __METHOD__,
                (is_object($user) ? get_class($user) : gettype($user))
            ));
        }
        if (null !== $password && ! is_string($password)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a string password argument; received %s',
                __METHOD__,
                (is_object($password) ? get_class($password) : gettype($password))
            ));
        }

        $info = $user;
        if ($password) {
            $info .= ':' . $password;
        }

        if ($info === $this->_userInfo) {
            // Do nothing if no change was made.
            return clone $this;
        }

        $new = clone $this;
        $new->_userInfo = $info;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHost($host)
    {
        if (! is_string($host)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a string argument; received %s',
                __METHOD__,
                (is_object($host) ? get_class($host) : gettype($host))
            ));
        }

        if ($host === $this->_host) {
            // Do nothing if no change was made.
            return clone $this;
        }

        $new = clone $this;
        $new->_host = $host;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPort($port)
    {
        if (! is_numeric($port) && $port !== null) {
            throw new InvalidArgumentException(sprintf(
                'Invalid port "%s" specified; must be an integer, an integer string, or null',
                (is_object($port) ? get_class($port) : gettype($port))
            ));
        }

        if ($port !== null) {
            $port = (int) $port;
        }

        if ($port === $this->_port) {
            // Do nothing if no change was made.
            return clone $this;
        }

        if ($port !== null && $port < 1 || $port > 65535) {
            throw new InvalidArgumentException(sprintf(
                'Invalid port "%d" specified; must be a valid TCP/UDP port',
                $port
            ));
        }

        $new = clone $this;
        $new->_port = $port;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPath($path)
    {
        if (! is_string($path)) {
            throw new InvalidArgumentException(
                'Invalid path provided; must be a string'
            );
        }

        if (strpos($path, '?') !== false) {
            throw new InvalidArgumentException(
                'Invalid path provided; must not contain a query string'
            );
        }

        if (strpos($path, '#') !== false) {
            throw new InvalidArgumentException(
                'Invalid path provided; must not contain a URI fragment'
            );
        }

        $path = $this->_filterPath($path);

        if ($path === $this->_path) {
            // Do nothing if no change was made.
            return clone $this;
        }

        $new = clone $this;
        $new->_path = $path;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withQuery($query)
    {
        if (! is_string($query)) {
            throw new InvalidArgumentException(
                'Query string must be a string'
            );
        }

        if (strpos($query, '#') !== false) {
            throw new InvalidArgumentException(
                'Query string must not include a URI fragment'
            );
        }

        $query = $this->_filterQuery($query);

        if ($query === $this->_query) {
            // Do nothing if no change was made.
            return clone $this;
        }

        $new = clone $this;
        $new->_query = $query;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withFragment($fragment)
    {
        if (! is_string($fragment)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a string argument; received %s',
                __METHOD__,
                (is_object($fragment) ? get_class($fragment) : gettype($fragment))
            ));
        }

        $fragment = $this->_filterFragment($fragment);

        if ($fragment === $this->_fragment) {
            // Do nothing if no change was made.
            return clone $this;
        }

        $new = clone $this;
        $new->_fragment = $fragment;

        return $new;
    }

    /**
     * Parse a URI into its parts, and set the properties
     *
     * @param string $uri
     */
    protected function _parseUri($uri)
    {
        $parts = parse_url($uri);

        if (false === $parts) {
            throw new \InvalidArgumentException(
                'The source URI string appears to be malformed'
            );
        }

        $this->_scheme    = isset($parts['scheme'])   ? $this->_filterScheme($parts['scheme']) : '';
        $this->_userInfo  = isset($parts['user'])     ? $parts['user']     : '';
        $this->_host      = isset($parts['host'])     ? $parts['host']     : '';
        $this->_port      = isset($parts['port'])     ? $parts['port']     : null;
        $this->_path      = isset($parts['path'])     ? $this->_filterPath($parts['path']) : '';
        $this->_query     = isset($parts['query'])    ? $this->_filterQuery($parts['query']) : '';
        $this->_fragment  = isset($parts['fragment']) ? $this->_filterFragment($parts['fragment']) : '';

        if (isset($parts['pass'])) {
            $this->_userInfo .= ':' . $parts['pass'];
        }
    }

    /**
     * Create a URI string from its various parts
     *
     * @param string $scheme
     * @param string $authority
     * @param string $path
     * @param string $query
     * @param string $fragment
     * @return string
     */
    protected static function _createUriString($scheme, $authority, $path, $query, $fragment)
    {
        $uri = '';

        if (! empty($scheme)) {
            $uri .= sprintf('%s://', $scheme);
        }

        if (! empty($authority)) {
            $uri .= $authority;
        }

        if ($path) {
            if (empty($path) || '/' !== substr($path, 0, 1)) {
                $path = '/' . $path;
            }

            $uri .= $path;
        }

        if ($query) {
            $uri .= sprintf('?%s', $query);
        }

        if ($fragment) {
            $uri .= sprintf('#%s', $fragment);
        }

        return $uri;
    }

    /**
     * Is a given port non-standard for the current scheme?
     *
     * @param string $scheme
     * @param string $host
     * @param int $port
     * @return bool
     */
    protected function _isNonStandardPort($scheme, $host, $port)
    {
        if (! $scheme) {
            return true;
        }

        if (! $host || ! $port) {
            return false;
        }

        return ! isset($this->_allowedSchemes[$scheme]) || $port !== $this->_allowedSchemes[$scheme];
    }

    /**
     * Filters the scheme to ensure it is a valid scheme.
     *
     * @param string $scheme Scheme name.
     *
     * @return string Filtered scheme.
     */
    protected function _filterScheme($scheme)
    {
        $scheme = strtolower($scheme);
        $scheme = preg_replace('#:(//)?$#', '', $scheme);

        if (empty($scheme)) {
            return '';
        }

        if (! array_key_exists($scheme, $this->_allowedSchemes)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported scheme "%s"; must be any empty string or in the set (%s)',
                $scheme,
                implode(', ', array_keys($this->_allowedSchemes))
            ));
        }

        return $scheme;
    }

    /**
     * Filters the path of a URI to ensure it is properly encoded.
     *
     * @param string $path
     * @return string
     */
    protected function _filterPath($path)
    {
        $path = preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . ':@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/',
            [$this, '_urlEncodeChar'],
            $path
        );

        if (empty($path)) {
            // No path
            return $path;
        }

        if ($path[0] !== '/') {
            // Relative path
            return $path;
        }

        // Ensure only one leading slash, to prevent XSS attempts.
        return '/' . ltrim($path, '/');
    }

    /**
     * Filter a query string to ensure it is propertly encoded.
     *
     * Ensures that the values in the query string are properly urlencoded.
     *
     * @param string $query
     * @return string
     */
    protected function _filterQuery($query)
    {
        if (! empty($query) && strpos($query, '?') === 0) {
            $query = substr($query, 1);
        }

        $parts = explode('&', $query);
        foreach ($parts as $index => $part) {
            list($key, $value) = $this->_splitQueryValue($part);
            if ($value === null) {
                $parts[$index] = $this->_filterQueryOrFragment($key);
                continue;
            }
            $parts[$index] = sprintf(
                '%s=%s',
                $this->_filterQueryOrFragment($key),
                $this->_filterQueryOrFragment($value)
            );
        }

        return implode('&', $parts);
    }

    /**
     * Split a query value into a key/value tuple.
     *
     * @param string $value
     * @return array A value with exactly two elements, key and value
     */
    protected function _splitQueryValue($value)
    {
        $data = explode('=', $value, 2);
        if (1 === count($data)) {
            $data[] = null;
        }
        return $data;
    }

    /**
     * Filter a fragment value to ensure it is properly encoded.
     *
     * @param null|string $fragment
     * @return string
     */
    protected function _filterFragment($fragment)
    {
        if (! empty($fragment) && strpos($fragment, '#') === 0) {
            $fragment = substr($fragment, 1);
        }

        return $this->_filterQueryOrFragment($fragment);
    }

    /**
     * Filter a query string key or value, or a fragment.
     *
     * @param string $value
     * @return string
     */
    protected function _filterQueryOrFragment($value)
    {
        return preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/',
            [$this, '_urlEncodeChar'],
            $value
        );
    }

    /**
     * URL encode a character returned by a regex.
     *
     * @param array $matches
     * @return string
     */
    protected function _urlEncodeChar(array $matches)
    {
        return rawurlencode($matches[0]);
    }
}
