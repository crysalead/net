<?php
namespace Lead\Net\Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\MessageInterface;

/**
 * PSR-7 Message interoperability trait
 */
trait MessageTrait
{
    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion(): string
    {
        return $this->version();
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * @param  string $version HTTP protocol version
     * @return self
     */
    public function withProtocolVersion($version): MessageInterface
    {
        $message = clone $this;
        $message->version($version);
        return $message;
    }

    /**
     * Retrieves all message header values.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * While header names are not case-sensitive, getHeaders() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return array Returns an associative array of the message's headers. Each
     *               key MUST be a header name, and each value MUST be an array of strings
     *               for that header.
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->headers() as $name => $value) {
            $headers[$name] = $value->data();
        }
        return $headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param  string $name Case-insensitive header field name.
     * @return bool         Returns true if any header names match the given header
     *                      name using a case-insensitive string comparison. Returns false if
     *                      no matching header name is found in the message.
     */
    public function hasHeader($name): bool
    {
        $headers = $this->headers();
        return isset($headers[$name]);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param  string   $name Case-insensitive header field name.
     * @return array    An array of string values as provided for the given
     *                  header. If the header does not appear in the message, this method MUST
     *                  return an empty array.
     */
    public function getHeader($name): array
    {
        $headers = $this->headers();
        return $headers[$name]->data();
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param  string $name Case-insensitive header field name.
     * @return string       A string of values as provided for the given header
     *                      concatenated together using a comma. If the header does not appear in
     *                      the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name): string
    {
        $headers = $this->headers();
        return $headers[$name]->value();
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * @param  string          $name Case-insensitive header field name.
     * @param  string|string[] $value Header value(s).
     * @return self
     *
     * @throws InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($name, $value): MessageInterface
    {
        $message = clone $this;
        $headers = $message->headers();
        $headers[$name] = $value;
        return $message;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * @param  string       $name  Case-insensitive header field name to add.
     * @param  string|array $value Header value(s).
     * @return self
     *
     * @throws InvalidArgumentException for invalid header names or values.
     */
    public function withAddedHeader($name, $value): MessageInterface
    {
        $message = clone $this;
        $headers = $message->headers();
        $headers[$name][] = $value;
        return $message;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * @param  string $name Case-insensitive header field name to remove.
     * @return self
     */
    public function withoutHeader($name): MessageInterface
    {
        $message = clone $this;
        $headers = $message->headers();
        unset($headers[$name]);
        return $message;
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body as a stream.
     */
    public function getBody(): StreamInterface
    {
        return $this->stream();
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * @param  StreamInterface $body Body.
     * @return self
     *
     * @throws InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        $message = clone $this;
        $stream = $message->stream();
        $stream->close();
        $stream->add($body);
        return $message;
    }
}
