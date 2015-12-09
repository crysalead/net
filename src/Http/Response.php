<?php
namespace Lead\Net\Http;

/**
 * Parses and stores the status, headers and body of an HTTP response.
 */
class Response extends \Lead\Net\Http\Message
{
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
     *                      - `'status'`  _mixed_ : null
     */
    public function __construct($config = [])
    {
        $defaults = [
            'status'  => []
        ];
        $config += $defaults;

        parent::__construct($config);

        if (isset($this->_headers['Transfer-Encoding'])) {
            $this->_body = $this->_httpChunkedDecode($this->_body);
        }
        if ($config['status']) {
            $this->status($status);
        }
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
     * Looks at the WWW-Authenticate. Will return array of key/values if digest.
     *
     * @param  string $header value of WWW-Authenticate
     * @return array
     */
    public function digest()
    {
        if (!isset($this->_headers['WWW-Authenticate'])) {
            return [];
        }
        $auth = $this->_classes['auth'];
        return $auth::decode($this->_headers['WWW-Authenticate']);
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
            $headers = [
                'Expires: Mon, 26 Jul 1997 05:00:00 GMT',
                'Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0',
                'Pragma: no-cache'
            ];
        } else {
            $expires = is_int($expires) ? $expires : strtotime($expires);
            $headers = [
                'Expires: ' . gmdate('D, d M Y H:i:s', $expires) . ' GMT',
                'Cache-Control: max-age=' . ($expires - time()),
                'Pragma: no-cache'
            ];
        }
        foreach ($headers as $header) {
            $this->headers()->add($header);
        }
    }

    /**
     * Decodes content bodies transferred with HTTP chunked encoding.
     *
     * @link http://en.wikipedia.org/wiki/Chunked_transfer_encoding Wikipedia: Chunked encoding
     *
     * @param  string $body A chunked HTTP message body.
     * @return string       Returns the value of `$body` with chunks decoded, but only if the value of the
     *                      `Transfer-Encoding` header is set to `'chunked'`. Otherwise, returns `$body`
     *                      unmodified.
     */
    protected function _httpChunkedDecode($body)
    {
        if (!isset($this->_headers['Transfer-Encoding']) || stripos($this->_headers['Transfer-Encoding'], 'chunked') === false) {
            return $body;
        }
        $stream = fopen('data://text/plain;base64,' . base64_encode($body), 'r');
        stream_filter_append($stream, 'dechunk');
        return trim(stream_get_contents($stream));
    }

    /**
     * Magic method to convert object to string.
     *
     * @return string
     */
    public function toString()
    {
        $protocol = $this->protocol();
        $status = "{$protocol} {$this->_status[0]} {$this->status[1]}\r\n";
        return $status . (string) $this->_headers . (string) $this->_body;
    }

    /**
     * Creates a response instance from an entire HTTP message including HTTP status headers and body.
     *
     * @param  string $message The full HTTP message.
     * @param  string $options Additionnal options.
     * @return object          Returns a request instance.
     */
    public static function create($message, $options = [])
    {
        $parts = explode("\r\n\r\n", $message, 2);
        list($headers, $body) = $parts;
        $headers = str_replace("\r", '', explode("\n", $headers));

        $headers = array_filter($headers);
        $extra['headers'] = $headers;

        preg_match('/HTTP\/(\d+\.\d+)\s+(\d+)(?:\s+(.*))?/i', reset($headers), $matches);

        if ($matches) {
            $extra['version'] = $matches[1];
            $extra['status'] = [$matches[2], isset($matches[3]) ? $matches[3] : ''];
        }

        $extra['body'] = $body;
        return new static($options + $extra);
    }

    /**
     * Exports a `Response` instance to an array.
     *
     * @param  mixed $response A `Request` instance.
     * @param  array $options  Options.
     * @return array           The export array.
     */
    public static function toArray($response, $options = [])
    {
        return [
            'status'   => $response->method(),
            'scheme'   => $response->scheme(),
            'version'  => $response->version(),
            'host'     => $response->host(),
            'port'     => $response->port(),
            'path'     => $response->path(),
            'username' => $response->username(),
            'password' => $response->password(),
            'url'      => $response->url(),
            'headers'  => $request->headers(),
            'stream'   => $response->stream()
        ];
    }
}

?>