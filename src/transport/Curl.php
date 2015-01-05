<?php
namespace net\transport;

use set\Set;

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
     * Requests.
     *
     * @var array
     */
    protected $_requests = [];

    /**
     * Active handles.
     *
     * @var array
     */
    protected $_handles = [];

    /**
     * Responses.
     *
     * @var array
     */
    protected $_responses = [];

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'stream' => 'storage\stream\Stream'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_curl = curl_multi_init();
    }

    public function __call($method, $options = [])
    {
        $request = isset($options[0]) ? $options[0] : [];
        return $this(compact('method') + $request);
    }

    /**
     * Adds a query.
     */
    public function __invoke($request = [], $response = [])
    {
        $defaults = [
            'port'    => 80,
            'url'     => null,
            'timeout' => 30,
            'body'    => null,
            'curl'    => []
        ];
        $request += $defaults;

        $defaults = [
            'body'    => null,
            'file'    => null
        ];
        $response += $defaults;

        $curl = $request['curl'] + $this->_curlDefaults($request, $response);

        $stream = $this->_classes['stream'];

        if (isset($curl[CURLOPT_INFILE])) {
            $out = new $stream([
                'file' => $curl[CURLOPT_INFILE],
                'mode' => 'r'
            ]);
        } elseif (is_object($request['body'])) {
            $out = $request['body'];
        } else {
            $out = new $stream(['data' => $request['body']]);
        }

        $curl[CURLOPT_INFILESIZE] = $out->size();

        if (isset($curl[CURLOPT_FILE])) {
            $in = new $stream(['file' => $curl[CURLOPT_FILE]]);
        } elseif (is_object($response['body'])) {
            $in = $response['body'];
        } else {
            $in = new $stream();
        }

        $handle = curl_init();
        $id = (integer) $handle;
        curl_setopt_array($handle, $curl);

        $this->_requests[$id] = [
            'handle' => $handle,
            'body'   => $out
        ];

        $this->_responses[$id] = [
            'url'     => $request['url'],
            'meta'    => [],
            'headers' => [],
            'body'    => $in
        ];

        return $id;
    }

    protected function _curlDefaults($request, $response)
    {
        $defaults = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER         => false,
            CURLOPT_UPLOAD         => true,
            CURLOPT_PORT           => $request['port'],
            CURLOPT_URL            => $request['url'],
            CURLOPT_CONNECTTIMEOUT => $request['timeout'],
            CURLOPT_HEADERFUNCTION => function($handle, $header) {
                if (trim($header)) {
                    $this->_responses[(integer) $handle]['headers'][] = $header;

                }
                return strlen($header);
            },
            CURLOPT_READFUNCTION   => function($handle, $fp, $length) {
                $data = $this->_handles[(integer) $handle]['body']->read($length);
                return $data;
            },
            CURLOPT_WRITEFUNCTION  => function($handle, $data) {
                $this->_responses[(integer) $handle]['body']->write($data);
                return strlen($data);
            }
        ];

        if (isset($request['version'])) {
            $defaults[CURLOPT_HTTP_VERSION] = $request['version'] === '1.1' ? CURL_HTTP_VERSION_1_1 : CURL_HTTP_VERSION_1_0;
        }

        if (isset($request['method'])) {
            $defaults[CURLOPT_CUSTOMREQUEST] = strtoupper($request['method']);
        }

        if (isset($request['headers'])) {
            $defaults[CURLOPT_HTTPHEADER] = $request['headers'];
        }

        if (isset($response['file'])) {
            $curl[CURLOPT_FILE] = $response['file'];
        }

        return $defaults;
    }

    /**
     * Runs until all outstanding requests have completed.
     *
     * @param  integer $max The number of simultaneous allowed connections.
     * @return array        The array of responses.
     */
    public function send($max = 10)
    {
        do {
            $this->select();
            $this->process($max);
        } while ($this->_running > 0);

        return $this->_responses;
    }

    /**
     * Process one curl exec call only.
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
            }

            $this->_responses[$id]['meta'] = $meta;
            $mime = curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: true;
            $this->_responses[$id]['body']->mime($mime);
            $this->_responses[$id]['body']->rewind();

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
        $requests = array_splice($this->_requests, 0, $nb);
        foreach ($requests as $request) {
            $this->_handles[(integer) $request['handle']] = $request;
            curl_multi_add_handle($this->_curl, $request['handle']);
        }
    }

    /**
     * Fill the multi handle up to the maximum allowed connections.
     *
     * @param  integer $timeout Time, in seconds, to wait for a response.
     * @return integer          The number of descriptors contained in the descriptor sets.
     */
    public function select($timeout = 1.0)
    {
        if (($result = curl_multi_select($this->_curl, $timeout)) === -1) {
            usleep(100); // See: https://bugs.php.net/bug.php?id=61141
        }
        return $result;
    }

    /**
     * Checks if there's some received responses.
     *
     * @return boolean Returns `true` if some responses are available, `false` otherwise.
     */
    public function hasResponses()
    {
        return count($this->_responses);
    }

    /**
     * Unstack a response from received responses.
     *
     * @return mixed A response array or `false` if there's no more responses left.
     */
    public function response()
    {
        $response = array_shift($this->_responses);
        return $response !== null ? $response : false;
    }

    /**
     * Checks if the end of tranfers has been reached.
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
