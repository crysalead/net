<?php
namespace Lead\Net\Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 Request interoperability trait
 */
trait RequestTrait
{
    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function getMethod()
    {
        return $this->method();
    }

    /**
     * Returns a new instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * @param  string $method Case-sensitive method.
     * @return self
     *
     * @throws InvalidArgumentException for invalid HTTP methods.
     */
    public function withMethod($method)
    {
        $request = clone $this;
        $request->method($method);
        return $request;
    }

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget()
    {
        return $this->requestTarget();
    }

    /**
     * Returns a new instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-2.7
     *
     * @param  mixed $requestTarget
     * @return self
     */
    public function withRequestTarget($requestTarget)
    {
        $request = clone $this;
        if (preg_match('~^(?:[a-z]+:)?//~i', $requestTarget)) {
            $result = parse_url($requestTarget);
            if (isset($result['user'])) {
                $result['username'] = $result['user'];
                unset($result['user']);
            }
            if (isset($result['pass'])) {
                $result['password'] = $result['pass'];
                unset($result['pass']);
            }
            foreach ($result as $method => $value) {
                $request->$method($value);
            }
            $request->mode('absolute');
            return $request;
        }
        if (preg_match("~^(?:(?P<username>[^:]+)(?::(?P<password>[^@]+))?@)?(?P<host>[^/]+)$~", $requestTarget, $matches)) {
            $request->username($matches['username'] ?: null);
            $request->password($matches['password'] ?: null);
            $request->host($matches['host']);
            $request->mode('authority');
            return $request;
        }
        if ($requestTarget === '*') {
            $request->mode('asterisk');
            return $request;
        }
        $parts = explode('#', $requestTarget);
        if (isset($parts[1])) {
            $request->fragment($parts[1]);
        }
        $parts = explode('?', $parts[0]);
        if (isset($parts[1])) {
            $request->query($parts[1]);
        }
        $request->path($parts[0]);
        return $request;
    }

    /**
     * Retrieves the URI instance.
     *
     * Updating the URI will have no effect on the request. use `withUri()` to apply any changes.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     *
     * @return UriInterface Returns a UriInterface instance representing the URI of the request.
     */
    public function getUri()
    {
        $uri = new Uri($this->url());
        if ($this->username()) {
            $uri = $uri->withUserInfo($this->username(), $this->password());
        }
        return $uri;
    }

    /**
     * Returns an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     *
     * @param  UriInterface $uri          New request URI to use.
     * @param  bool         $preserveHost Preserve the original state of the Host header.
     * @return self
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $request = clone $this;
        $request->scheme($uri->getScheme());
        $userInfo = $uri->getUserInfo();
        $parts = explode(':', $userInfo);

        $request->username($parts[0] ?: null);
        $request->password(!empty($parts[1]) ? $parts[1] : null);
        $request->port($uri->getPort());

        if ($preserveHost) {
            $host = $request->headers['Host'];
            $request->host($uri->getHost());
            $request->headers['Host'] = $host;
        } else {
            $request->host($uri->getHost());
        }

        $request->path($uri->getPath());
        $request->query($uri->getQuery());
        $request->fragment($uri->getFragment());

        return $request;
    }
}