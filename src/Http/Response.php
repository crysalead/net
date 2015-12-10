<?php
namespace Lead\Net\Http;

use Lead\Net\NetException;

/**
 * Parses and stores the status, headers and body of an HTTP response.
 */
class Response extends \Lead\Net\Http\Message
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'   => 'Lead\Net\Http\Response::toArray'
    ];

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
            'status'     => [],
            'setCookies' => []
        ];
        $config += $defaults;

        parent::__construct($config);

        if ($config['status']) {
            $this->status($config['status']);
        }
        $setCookies = $this->headers()->setCookies();
        foreach ($config['setCookies'] as $key => $value) {
            $setCookies[$key] = $value;
        }
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
     * Magic method to convert object to string.
     *
     * @return string
     */
    public function toString()
    {
        static::_setContentLength($this);
        return $this->line() . "\r\n" . (string) $this->_headers . (string) $this->_body;
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
        if (count($parts) < 2) {
            throw new NetException("The CRLFCRLF separator between headers and body is missing.");
        }
        $response = new static($options);

        list($header, $body) = $parts;

        $headers = str_replace("\r", '', explode("\n", $header));
        $headers = array_filter($headers);

        preg_match('/HTTP\/(\d+\.\d+)\s+(\d+)(?:\s+(.*))?/i', array_shift($headers), $matches);

        $response->headers()->add($headers);
        $headers = $response->headers();

        if ($matches) {
            $response->version($matches[1]);
            $response->status([$matches[2], isset($matches[3]) ? $matches[3] : '']);
        }

        if (isset($headers['Transfer-Encoding']) && $headers['Transfer-Encoding']->value() === 'chunked') {
            $stream = fopen('data://text/plain;base64,' . base64_encode($body), 'r');
            stream_filter_append($stream, 'dechunk');
            $body = trim(stream_get_contents($stream));
        }

        $response->plain($body);
        return $response;
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
        static::_setContentLength($response);
        return [
            'status'  => $response->status(),
            'version' => $response->version(),
            'headers' => $response->headers(),
            'body'    => $response->stream()
        ];
    }
}
