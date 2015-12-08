<?php
namespace Lead\Net\Http;

/**
 * Parses and stores the status, headers and body of an HTTP response.
 */
class Response extends \Lead\Net\Http\Message
{
    /**
     * Status codes.
     *
     * @var array
     */
    protected $_statuses = array(
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
    );

    /**
     * Adds config values to the public properties when a new object is created.
     *
     * @param array $config Configuration options:
     *                      - `'version'` _string_: '1.1'
     *                      - `'headers'` _array_ : []
     *                      - `'body'`    _mixed_ : null
     *                      - `'message'` _string_: null
     *                      - `'status'`  _mixed_ : null
     *                      - `'type'`    _string_: null
     */
    public function __construct($config = [])
    {
        $defaults = [
            'message' => null,
            'status'  => [],
            'type'    => null,
        ];
        $config += $defaults;

        if (!empty($config['message'])) {
            $config = $this->_parseMessage($config['message']) + $config;
        }

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
     * @param  string $key    Optional. Set to 'code' or 'message' to return just the code or message
     *                        of the status, otherwise returns the full status header.
     * @param  string $status The code or message of the status you wish to set.
     * @return string         Returns the full HTTP status, with version, code and message.
     */
    public function status($status = [])
    {
        if (func_num_args() === 0) {
            $status = $this->headers()->status();
            preg_match('/HTTP\/(\d+\.\d+)\s+(\d+)(?:\s+(.*))?/i', $status, $matches);
            if ($matches) {
                return [$matches[2], isset($matches[3]) ? $matches[3] : ''];
            }
            return;
        }
        $message = null;

        if (is_array($status)) {
            $code = isset($status[0]) ? (string) $status[0] : '200';
            $message = isset($status[1]) ? (string) $status[1] : null;
        } else {
            $code = $status;
        }
        if (!$message && isset($this->_statuses[$code])) {
            $message = $this->_statuses[$code];
        }
        $this->headers()->status("{$this->protocol} {$this->status['code']} {$this->status['message']}");
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
     * Accepts an entire HTTP message including headers and body, and parses it into a message body
     * an array of headers, and the HTTP status.
     *
     * @param  string $body The full body of the message.
     * @return              After parsing out other message components, returns just the message body.
     */
    protected function _parseMessage($body)
    {
        $options = [];
        $parts = explode("\r\n\r\n", $body, 2);
        list($headers, $body) = $parts;
        $headers = str_replace("\r", '', explode("\n", $headers));

        $headers = array_filter($headers);
        $options['headers'] = $headers;

        preg_match('/HTTP\/(\d+\.\d+)\s+(\d+)(?:\s+(.*))?/i', reset($headers), $matches);

        if ($matches) {
            $options['version'] = $matches[1];
            $options['status'] = [$matches[2], isset($matches[3]) ? $matches[3] : ''];
        }

        $options['body'] =  $body;
        return $options;
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
}

?>