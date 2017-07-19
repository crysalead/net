<?php
namespace Lead\Net\Transport\Protocol\Http;

use InvalidArgumentException;
use Lead\Net\NetException;
use Lead\Set\Set;

/**
 * A cURL-based Transport Layer.
 *
 * - Support parallel requests.
 * - More reliable & faster than the classic HTTP stream wrapper for big uploads.
 */
class Curl
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * The multi handler resource.
     *
     * @var resource
     */
    protected $_curl = null;

    /**
     * Number of running curl operations.
     *
     * @var integer
     */
    protected $_running = 0;

    /**
     * Queue of requests.
     *
     * @var array
     */
    protected $_queue = [];

    /**
     * Stack of running queries.
     *
     * @var array
     */
    protected $_handles = [];

    /**
     * Storage for storing responses.
     *
     * @var array
     */
    protected $_responses = [];

    /**
     * Responses.
     *
     * @var array
     */
    protected $_results = [];

    /**
     * The Constructor.
     *
     * @param array $config The config array. Possible values are:
     *                      -`'classes'` _array_: the class dependencies.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'stream' => 'Lead\Storage\Stream\Stream',
                'socket' => 'Lead\Net\Transport\Socket'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_curl = curl_multi_init();
    }

    /**
     * Adds a query.
     *
     * @param mixed $request The request content.
     * @param array $params  The parameters to pass to the `__invoke()` method.
     */
    public function __invoke($request, $options = [])
    {
        $curlOptions = isset($options['curl']) ? $options['curl'] : [];
        $config = [
            'client'  => $this,
            'options' => $curlOptions + $this->_requestOptions($request, $options) + $this->_curlOptions($options),
            'classes' => $this->_classes
        ];

        if (isset($options[CURLOPT_INFILE])) {
            $body = fopen($options[CURLOPT_INFILE], 'r');
        } elseif (is_array($request)) {
            $body = isset($request['body']) ? $request['body'] : '';
        } else {
            $body = $request->stream();
        }
        $socket = $this->_classes['socket'];

        return new $socket(['data' => $body] + $config);
    }

    /**
     * Initialize a socket to be ready to send data.
     *
     * @param integer $max The number of simultaneous allowed connections.
     */
    protected function _initRequest($socket, $response)
    {
        $stream = $this->_classes['stream'];

        $curlOptions = $socket->options();
        $curlOptions[CURLOPT_INFILESIZE] = $socket->outgoing()->length();

        if (isset($curlOptions[CURLOPT_FILE])) {
            $response->body(fopen($curlOptions[CURLOPT_FILE], 'w+'));
        }
        $socket->incoming($response->stream());

        if (!$handle = curl_init()) {
            throw new ClientException('Unable to create a new cURL handle');
        }
        curl_setopt_array($handle, $curlOptions);
        return $handle;
    }

    protected function _requestOptions($request, $options)
    {
        if (is_array($request)) {
            $query = $request;
        } else {
            $query = $request->export();
            $query['headers'] = $request->headers()->to('list');
        }

        if (!isset($query['url'])) {
            throw new InvalidArgumentException("Missing URL.");
        }

        $curlOptions[CURLOPT_URL] = $query['url'];

        if (isset($query['version'])) {
            $curlOptions[CURLOPT_HTTP_VERSION] = $query['version'] === '1.1' ? CURL_HTTP_VERSION_1_1 : CURL_HTTP_VERSION_1_0;
        }

        if (isset($query['port'])) {
            $curlOptions[CURLOPT_PORT] = $query['port'];
        }

        if (isset($query['headers'])) {
            $curlOptions[CURLOPT_HTTPHEADER] = array_merge($query['headers'], ['Expect:']);
        } else {
            $curlOptions[CURLOPT_HTTPHEADER] = ['Expect:'];
        }

        $method = isset($options['method']) ? $options['method'] : (isset($query['method']) ? $query['method'] : null);

        if (!$method) {
            return $curlOptions;
        }

        $method = $curlOptions[CURLOPT_CUSTOMREQUEST] = strtoupper($method);

        switch ($method) {
            case 'HEAD':
                $curlOptions[CURLOPT_NOBODY] = true;
                break;
            case 'GET':
                $curlOptions[CURLOPT_HTTPGET] = true;
                break;
            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
            case 'OPTIONS':
                $curlOptions[CURLOPT_UPLOAD] = true;
                break;
        }

        return $curlOptions;
    }

    protected function _curlOptions($options)
    {
        $defaults = [
            'connectionTimeout' => 10,
            'timeout'           => 10,
            'proxy'             => null,
            'maxRedirects'      => 10,
            'ignoreErrors'      => true,
            'verifyPeer'        => 1,
            'verifyHost'        => 2
        ];

        $options += $defaults;

        $canFollow = !ini_get('safe_mode') && !ini_get('open_basedir');

        $curlOptions = [
            CURLOPT_ENCODING        => true,
            CURLOPT_RETURNTRANSFER  => false,
            CURLOPT_CONNECTTIMEOUT  => $options['connectionTimeout'],
            CURLOPT_FOLLOWLOCATION  => $canFollow && $options['maxRedirects'] > 0,
            CURLOPT_MAXREDIRS       => $canFollow ? $options['maxRedirects'] : 0,
            CURLOPT_FAILONERROR     => !$options['ignoreErrors'],
            CURLOPT_SSL_VERIFYPEER  => $options['verifyPeer'],
            CURLOPT_SSL_VERIFYHOST  => $options['verifyHost'],
            CURLOPT_READFUNCTION    => function($handle, $fp, $length) {
                $socket = $this->_handles[(integer) $handle]['socket'];
                $stream = $socket->outgoing();
                $read = $stream->read($length);
                if ($read) {
                    $socket->trigger('sent', $read);
                }
                return $read;
            },
            CURLOPT_WRITEFUNCTION  => function($handle, $data) {
                $socket = $this->_handles[(integer) $handle]['socket'];
                $stream = $socket->incoming();
                $stream->write($data);

                if ($data) {
                    $socket->trigger('received', $data);
                }
                return strlen($data);
            },
            CURLOPT_HEADERFUNCTION => function($handle, $header) {
                preg_match('/HTTP\/(\d+\.\d+)\s+(\d+)(?:\s+(.*))?/i', $header, $matches);
                $response = $this->_handles[(integer) $handle]['response'];

                if ($matches) {
                    $response->version($matches[1]);
                    $response->status([$matches[2], isset($matches[3]) ? $matches[3] : '']);
                } else {
                    $response->headers()->push([$header]);
                }
                return strlen($header);
            }
        ];

        if ($options['timeout'] < 1) {
            $curlOptions[CURLOPT_TIMEOUT_MS] = $options['timeout'] * 1000;
        } else {
            $curlOptions[CURLOPT_TIMEOUT] = $options['timeout'];
        }

        if ($options['proxy']) {
            $curlOptions[CURLOPT_PROXY] = $options['proxy'];
        }

        return $curlOptions;
    }

    /**
     * HTTP facility, delegates calls to the `__invoke()` method by adding
     * the method name as the http method value in the query.
     *
     * @param string $method The HTTP method name to use for the query.
     * @param array  $params The parameters to pass to the `__invoke()` method.
     */
    public function __call($method, $params = [])
    {
        $message = $params[0];
        $response = $params[1];
        $options = isset($params[2]) ? $params[2] : [];
        $options['method'] = $method;

        return $this->send($message, $response, $options);
    }

    /**
     * Sends a request.
     *
     * @param  integer $max The number of simultaneous allowed connections.
     * @return array        The response.
     */
    public function send($request, $response, $options = [])
    {
        $this->push($request, $response, $options);
        $result = $this->flush(10, $options);
        return $response;
    }

    /**
     * Push a request in the queue.
     */
    public function push($request, $response, $options = [])
    {
        $socket = $request instanceof $this->_classes['socket'] ? $request : $this($request, $options);

        $this->_queue[$socket->id()] = [
            'socket'   => $socket,
            'response' => $response
        ];
    }

    /**
     * Runs until all outstanding requests have been completed.
     *
     * @param  integer $max The number of simultaneous allowed connections.
     * @return array        The array of responses.
     */
    public function flush($max = 10, $options = [])
    {
        $defaults = [
            'selectTimeout'  => 1.0
        ];

        $options += $defaults;

        do {
            $this->select($options['selectTimeout']);
            $this->process($max);
        } while ($this->_running > 0);

        $results = $this->_results;
        $this->_results = [];
        return $results;
    }

    /**
     * Fill the multi handle up to the maximum allowed connections.
     *
     * @param  integer $timeout Time, in seconds, to wait for a response.
     * @return integer          The number of descriptors contained in the descriptor sets.
     */
    public function select($timeout)
    {
        if (($result = curl_multi_select($this->_curl, $timeout)) === -1) {
            usleep(100); // See: https://bugs.php.net/bug.php?id=61141
        }
        return $result;
    }

    /**
     * Process one curl exec call only (non blocking call).
     *
     * @param integer $max The number of simultaneous allowed connections.
     */
    public function process($max = 10)
    {
        $this->_fillup($max);
        curl_multi_exec($this->_curl, $this->_running);

        while ($info = curl_multi_info_read($this->_curl)) {
            $handle = $info['handle'];
            $id = (integer) $handle;

            if (!isset($this->_handles[$id])) {
                continue;
            }

            $meta = [];
            $meta['transfer_stats'] = curl_getinfo($handle);

            if ($info['result'] !== CURLM_OK) {
                $meta['errno'] = $info['result'];
                $meta['error'] = curl_strerror($info['result']);
                throw new NetException($meta['error'], $meta['errno']);
            }

            $socket = $this->_handles[$id]['socket'];

            $mime = curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: true;
            $response = $this->_handles[$id]['response'];
            $response->mime($mime);
            $response->stream()->rewind();

            $socket->trigger('complete', $response);
            $this->_results[] = $response;

            curl_multi_remove_handle($this->_curl, $handle);
            curl_close($handle);
            unset($this->_handles[$id]);
        }
    }

    /**
     * Fill the multi handle up to the maximum allowed connections.
     *
     * @param integer $max The number of simultaneous allowed connections.
     */
    protected function _fillup($max)
    {
        $nb = $max - $this->_running;
        $list = array_splice($this->_queue, 0, $nb);
        foreach ($list as $item) {
            $handle = $this->_initRequest($item['socket'], $item['response']);
            $this->_handles[(integer) $handle] = $item;
            curl_multi_add_handle($this->_curl, $handle);
        }
    }

    /**
     * Checks the end of transmission.
     *
     * @return boolean Returns `true` if the end of tranfers has been reached, `false` otherwise.
     */
    public function eot()
    {
        if (!$this->_handles) {
            $this->process();
        }
        return !$this->_running;
    }

    /**
     * Clean up.
     */
    public function __destruct()
    {
        curl_multi_close($this->_curl);
        $this->_curl = null;
    }
}
