<?php
namespace Lead\Net\Http;

use Psr\Http\Message\StreamInterface;
use Lead\Net\NetException;
use Lead\Set\Set;
use Lead\Net\Part;
use Lead\Net\Http\Cookie\Cookie;

/**
 * Parses and stores the status, headers and body of an HTTP response.
 */
class Response extends \Lead\Net\Http\Message implements \Psr\Http\Message\ResponseInterface
{
    use Psr7\MessageTrait, Psr7\ResponseTrait;

    /**
     * HTTP Status.
     *
     * @var array
     */
    protected $_status = [200, 'OK'];

    /**
     * Status codes.
     *
     * @var array
     */
    protected $_statuses = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Method Failure',
        428 => 'Precondition Required',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        507 => 'Insufficient Storage'
    ];

    /**
     * Adds config values to the public properties when a new object is created.
     *
     * @param array $config Configuration options:
     *                      - `'status'`  _mixed_ : The response status (default: `[]`).
     */
    public function __construct($config = [])
    {
        $defaults = [
            'status'   => 200,
            'location' => null,
            'format'   => null,
            'locale'   => null
        ];
        $config = Set::merge($defaults, $config);

        parent::__construct($config);

        $this->status($config['status']);
        if ($config['locale']) {
            $this->locale($config['locale']);
        }

        if ($config['location']) {
            $this->redirect($config['location']);

        }
    }

    /**
     * Performs a format negotiation from a `Request` object, by iterating over the accepted
     * content types in sequence, from most preferred to least.
     *
     * @param  object $request        A request instance.
     * @param  array  $allowedFormats Some allowed formats.
     */
    public function negotiate($request, $allowedFormats = null)
    {
        $media = $this->_classes['media'];
        foreach ($request->accepts() as $mime => $value) {
            if ($format = $media::suitable($request, $mime, $allowedFormats)) {
                $this->format($format);
                return;
            }
        }
        $mimes = join('", "', array_keys($request->accepts()));
        throw new NetException("Unsupported Media Type: `{$mimes}`.", 415);
    }

    /**
     * Gets/sets the content langage.
     *
     * @param  string|array $locale A locale `'en'`, `'en_US'` or `'de_DE'`.
     * @return string|null          Returns the locale.
     */
    public function locale($locale = null)
    {
        if (!func_num_args()) {
            $locales = $this->hasHeader('Content-Language') ? $this->getHeader('Content-Language') : [];
            return reset($locales);
        }
        $headers = $this->headers();
        $headers['Content-Language'] = $locale;
        return $this;
    }

    /**
     * Returns the request line.
     *
     * @return string
     */
    public function line()
    {
        $status = $this->status();
        return $this->protocol(). ' ' . $status[0]. ' ' .$status[1];
    }

    /**
     * Sets/gets the status for the response.
     *
     * @param  string      $key    Optional. Set to 'code' or 'message' to return just the code or message
     *                             of the status, otherwise returns the full status header.
     * @param  string      $status The code or message of the status you wish to set.
     * @return string|self         Returns the full HTTP status, with version, code and message.
     */
    public function status($status = [])
    {
        if (func_num_args() === 0) {
            return $this->_status;
        }
        $message = null;

        if (is_array($status)) {
            $code = isset($status[0]) ? (integer) $status[0] : 200;
            $message = isset($status[1]) ? (string) $status[1] : null;
        } else {
            $code = (integer) $status;
        }
        if (!$message && isset($this->_statuses[$code])) {
            $message = $this->_statuses[$code];
        }
        $this->_status = [$code, $message];
        return $this;
    }

    /**
     * Get the status code of the response.
     *
     * @return integer The HTTP status code.
     */
    public function statusCode()
    {
        return $this->_status[0];
    }

    /**
     * Get the status message of the response.
     *
     * @return string The HTTP status message.
     */
    public function statusMessage()
    {
        return $this->_status[1];
    }

    /**
     * Get the success status of the message.
     *
     * @return boolean
     */
    public function success()
    {
        return $this->_status[0] >= 200 && $this->_status[0] < 300;
    }

    /**
     * Looks at the WWW-Authenticate. Will return array of key/values if digest.
     *
     * @param  string $header value of WWW-Authenticate
     * @return array
     */
    public function digest()
    {
        $headers = $this->headers();
        if (!isset($headers['WWW-Authenticate'])) {
            return [];
        }
        $auth = $this->_classes['auth'];
        return $auth::decode($headers['WWW-Authenticate']);
    }

    /**
     * Controls how or whether the client browser and web proxies should cache this response.
     *
     * @param  mixed $expires This can be a Unix timestamp indicating when the page expires, or a
     *                        string indicating the relative time offset that a page should expire, i.e. `"+5 hours".
     *                        Finally, `$expires` can be set to `false` to completely disable browser or proxy
     *                        caching.
     */
    public function cache($expires)
    {
        if ($expires === false) {
            $data = [
                'Expires: Mon, 26 Jul 1997 05:00:00 GMT',
                'Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0',
                'Pragma: no-cache'
            ];
        } else {
            $expires = is_int($expires) ? $expires : strtotime($expires);
            $data = [
                'Expires: ' . gmdate('D, d M Y H:i:s', $expires) . ' GMT',
                'Cache-Control: max-age=' . ($expires - time()),
                'Pragma: no-cache'
            ];
        }
        $headers = $this->headers();
        foreach ($data as $header) {
            $headers->push($header);
        }
    }

    /**
     * Renders a response by writing headers and output.
     *
     * @see https://bugs.php.net/bug.php?id=18029
     */
    public function dump()
    {
        $this->_setContentLength();
        header($this->line());
        $headers = $this->headers();
        foreach ($headers as $header) {
            header($header->to('header'));
        }
        if ($headers['Transfer-Encoding']->value() === 'chunked') {
            return;
        }

        if ($this->statusCode() === 204) {
            return;
        }
        if ($this->_stream->isSeekable()) {
            $this->_stream->rewind();
        }
        while (!$this->_stream->eof()) {
            echo $this->_stream->read();
            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }

    /**
     * Push additionnal data to a chunked response.
     *
     * @param mixed   $data   The formated data. If the passed data is a stream it'll be closed.
     * @param boolean $atomic Indicates if the $data can be chunked or must be send as a whole chunk.
     */
    public function push($data, $atomic = true, $options = [])
    {
        $headers = $this->headers();
        if ($headers['Transfer-Encoding']->value() !== 'chunked') {
            throw new NetException("Pushing is only supported in chunked transfer.");
        }

        $defaults = [
            'cast'   => true,
            'atomic' => true,
            'stream' => [],
            'encode' => []
        ];

        $options += $defaults;

        if ($data instanceof StreamInterface) {
            $stream = $data;
        } else {
            if ($options['cast']) {
                $media = $this->_classes['media'];
                $format = $this->format();

                if (!$format && !is_string($data)) {
                    throw new NetException("The data must be a string when no format is defined.");
                }
                $data = $media::encode($format, $data, $options['encode'], $this);
            }
            $stream = new Part(['data' => $data] + $options['stream']);
        }

        $length = $options['atomic'] && $stream->isSeekable() ? $stream->length() : $this->chunkSize();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            $chunk = $stream->read($length);
            $readed = strlen($chunk);
            if (!$readed) {
                break;
            }
            echo dechex($readed) . "\r\n" . $chunk . "\r\n";
            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
        $stream->close();
    }

    /**
     * Terminate a chunked transfer.
     */
    public function end()
    {
        $headers = $this->headers();
        if ($headers['Transfer-Encoding']->value() !== 'chunked') {
            return;
        }
        echo  "0\r\n\r\n";
    }

    /**
     * Set the Set-Cookie header
     *
     * @param  array $cookies The cookies.
     * @return self
     */
    public function applyCookies($cookies)
    {
        $headers = $this->headers();

        foreach ($cookies as $cookie) {
            $headers['Set-Cookie'][] = $cookie->toString();
        }
        return $this;
    }

    /**
     * Extract cookies.
     *
     * @return array The cookies array.
     */
    public function cookies($request)
    {
        $headers = $response->headers();
        if (!isset($headers['Set-Cookie'])) {
            return [];
        }
        $setCookies = [];
        foreach ($headers['Set-Cookie'] as $setCookieHeader) {
            $setCookie = Cookie::fromString($setCookieHeader);
            if (!$setCookie->domain()) {
                $setCookie->domain($request->hostname());
            }
            if (strpos($setCookie->path(), '/') !== 0) {
                $setCookie->path($this->_pathFrom($request));
            }
            $setCookies[] = $setCookie;
        }
        return $setCookies;
    }

    /**
     * Set a redirect location.
     *
     * @param string  $location The redirect location.
     * @param integer $status   The redirect HTTP status.
     */
    public function redirect($location, $status = 302)
    {
        if (!$location) {
            return;
        }
        $this->status($status);
        $headers = $this->headers();
        $headers['Location'] = $location;
        return $this;
    }

    /**
     * Exports a `Response` instance to an array.
     *
     * @param  array $options  Options.
     * @return array           The export array.
     */
    public function export($options = [])
    {
        $this->_setContentLength();
        return [
            'status'  => $this->status(),
            'version' => $this->version(),
            'headers' => $this->headers(),
            'body'    => $this->stream()
        ];
    }

    /**
     * Creates a response instance from an entire HTTP message including HTTP status headers and body.
     *
     * @param  string $message The full HTTP message.
     * @param  string $options Additionnal options.
     * @return object          Returns a request instance.
     */
    public static function parse($message, $options = [])
    {
        $parts = explode("\r\n\r\n", $message, 2);
        if (count($parts) < 2) {
            throw new NetException("The CRLFCRLF separator between headers and body is missing.");
        }
        $response = new static($options + ['format' => null]);

        list($header, $body) = $parts;

        $data = str_replace("\r", '', explode("\n", $header));
        $data = array_filter($data);

        preg_match('/HTTP\/(\d+\.\d+)\s+(\d+)(?:\s+(.*))?/i', array_shift($data), $matches);

        $headers = $response->headers();
        $headers->push($data);

        if ($matches) {
            $response->version($matches[1]);
            $response->status([$matches[2], isset($matches[3]) ? $matches[3] : '']);
        }

        if ($headers['Transfer-Encoding']->value() === 'chunked') {
            $decoded = '';
            while (!empty($body)) {
                $pos = strpos($body, "\r\n");
                $len = hexdec(substr($body, 0, $pos));
                $decoded .= substr($body, $pos + 2, $len);
                $body = substr($body, $pos + 2 + $len);
            }
            $body = $decoded;
        }

        $response->body($body);
        return $response;
    }
}
