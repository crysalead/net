<?php
namespace Lead\Net\Transport;

use Lead\Set\Set;

class Socket
{
    /**
     * Number of instantiated sockets.
     *
     * @var integer
     */
    protected static $_nb = 0;

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Socket id.
     *
     * @var integer
     */
    protected $_id = 0;

    /**
     * Transport client instance.
     *
     * @var object
     */
    protected $_client = null;

    /**
     * The transport client options.
     *
     * @var object
     */
    protected $_options = [];

    /**
     * The message body to send.
     *
     * @var mixed
     */
    protected $_body = null;

    /**
     * The outgoing stream.
     *
     * @var object
     */
    protected $_outgoing = null;

    /**
     * The incoming stream.
     *
     * @var object
     */
    protected $_incoming = null;

    /**
     * A callback array.
     *
     * @var array
     */
    protected $_callbacks = [];

    /**
     * The Constructor.
     *
     * @param array $config The config array. Possible values are:
     *                      -`'classes'`   _array_ : the class dependencies.
     *                      -`'client'`    _object_: the transport client instance.
     *                      -`'data'`      _mixed_ : the data to send.
     *                      -`'outgoing'`  _object_: the outgoing stream.
     *                      -`'incoming'`  _object_: the incoming stream.
     *                      -`'options'`   _array_ : the transport client options.
     *                      -`'callbacks'` _array_ : a callbacks array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'stream'   => 'Lead\Storage\Stream',
                'callback' => 'Lead\Transport\Callback'
            ],
            'client'    => null,
            'data'      => null,
            'outgoing'  => null,
            'incoming'  => null,
            'options'   => [],
            'callbacks' => []
        ];
        $config += $defaults;

        $this->_classes = $defaults['classes'];

        if (isset($config['classes'])) {
            $this->_classes = $config['classes'] + $this->_classes;
        }
        $this->_client = $config['client'];
        $this->_data = $config['data'];
        $this->_outgoing = $config['outgoing'];
        $this->_incoming = $config['incoming'];
        $this->_options = $config['options'];
        $this->_id = static::$_nb++;
    }

    public function id()
    {
        return $this->_id;
    }

    public function options()
    {
        return $this->_options;
    }

    public function data()
    {
        return $this->_data;
    }

    public function send($data)
    {
        $this->outgoing()->push($data);
    }

    public function incoming($incoming = null)
    {
        if (func_num_args()) {
             $this->_incoming = $incoming;
        }
        if ($this->_incoming === null) {
            $stream = $this->_classes['stream'];
            $this->_incoming = new $stream();
        }
        return $this->_incoming;
    }

    public function outgoing($outgoing = null)
    {
        if (func_num_args()) {
             $this->_outgoing = $outgoing;
        }
        if ($this->_outgoing === null) {
            $stream = $this->_classes['stream'];
            if (!is_object($this->_data)) {
                $this->_outgoing = new $stream(['data' => $this->_data]);
            } else {
                $this->_outgoing = $this->_data;
            }
        }
        return $this->_outgoing;
    }

    public function on($type, $closure)
    {
        if (!isset($this->_callbacks[$type])) {
            $this->_callbacks[$type] = [];
        }
        $callback = $this->_classes['callback'];
        $instance = new $callback([
            'closure' => $closure,
            'context' => $this
        ]);
        $this->_callbacks[$type][] = $instance;
        return $instance;
    }

    public function trigger($type, $value = null)
    {
        if (!isset($this->_callbacks[$type])) {
            return;
        }
        foreach ($this->_callbacks[$type] as $callback) {
            $callback->trigger($value);
        }
    }

}
