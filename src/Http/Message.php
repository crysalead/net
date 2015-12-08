<?php
namespace Lead\Net\Http;

use Lead\Set\Set;
use Lead\Net\NetException;

/**
 * HTTP Message class
 */
class Message extends \Lead\Net\Message
{
    /**
     * HTTP protocol version number
     *
     * @var string
     */
    protected $_version = '1.1';

    /**
     * Adds config values to the public properties when a new object is created.
     *
     * @param array $config Configuration options. Possible values are:
     *                      - `'version'`  _string_ : (defaults `'1.1'`).
     *                      - `'scheme'`   _string_ : (defaults `'http'`).
     *                      - `'type'`     _string_ : (defaults `null`).
     *                      - `'headers'`  _mixed_  : (defaults `[]`).
     */
    public function __construct($config = [])
    {
        $defaults = [
            'version' => '1.1',
            'scheme'  => 'http',
            'type'    => null,
            'headers' => [],
            'classes' => [
                'auth'    => 'Lead\Net\Http\Auth',
                'headers' => 'Lead\Net\Http\Headers',
                'media'   => 'Lead\Net\Http\Media',
                'stream'  => 'Lead\Storage\Stream\Stream'
            ]
        ];
        $config = Set::merge($defaults, $config);

        parent::__construct($config);

        $this->_version = $config['version'];

        if ($config['type']) {
            $this->type($config['type']);
        } elseif (isset($this->_headers['content-type'])) {
            $this->type($this->_headers['content-type']);
        }
    }

    /**
     * Gets protocol string.
     *
     * @return string
     */
    public function protocol()
    {
        return "HTTP/" . $this->version();
    }

    /**
     * Gets/sets the HTTP protocol version number.
     *
     * @return string      The version number.
     * @return string|self
     */
    public function version($version = null)
    {
        if (func_num_args() === 0) {
            return $this->_version;
        }
        $this->_version = $version;
        return $this;
    }

    /**
     * Gets/sets the Content-Type.
     *
     * @param  string      $type A full Content-Type i.e. `'application/json'`.
     * @return string|self
     */
    public function type($type = null)
    {
        if (func_num_args() === 0) {
            if (!isset($this->_headers['content-type'])) {
                return;
            }
            list($type) = explode(';', $this->_headers['content-type']->data(), 2);
            return $type;
        }

        if ($type === false) {
            unset($this->_headers['content-type']);
            return;
        }

        $this->_headers['content-type'] = $type;
        if (!$encoding = $this->encoding()) {
            $this->encoding('UTF-8');
        }
        return $this;
    }

    /**
     * Gets/sets the Content-Type charset encoding.
     *
     * @param  string      $charset A charset i.e. `'UTF-8'`.
     * @return string|self
     */
    public function encoding($charset = null)
    {
        if (!isset($this->_headers['Content-Type'])) {
            if (func_num_args() !== 0) {
                throw new NetException("Can't set a charset with no valid Content-Type defined.");
            }
            return;
        }
        $value = $this->_headers['content-type']->data();

        preg_match('/([-\w\/\.+]+)(;\s*?charset=(.+))?/i', $value, $matches);

        if (func_num_args() === 0) {
            return isset($matches[3]) ? strtoupper(trim($matches[3])) : null;
        }
        $this->_headers['Content-Type'] = $matches[1] . ($charset ? "; charset=" . strtoupper($charset) : "");
        return $this;
    }

    /**
     * Gets/sets the body of this message.
     *
     * @param  mixed       $value The data to set as body message.
     * @return string|self
     */
    public function body($value = null)
    {
        $media = $this->_classes['media'];
        $type = $this->type();

        if (func_num_args() === 1) {
            $this->stream($media::encode($type, $value));
            return $this;
        }
        return $media::decode($type, (string) $this->_body);
    }

}
