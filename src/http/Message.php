<?php
namespace net\http;

use set\Set;

/**
 * HTTP Message class
 */
class Message extends \net\Message
{
    /**
     * Specification version number
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
                'auth'    => 'net\http\Auth',
                'headers' => 'net\http\Headers',
                'media'   => 'net\http\Media',
                'stream'  => 'storage\stream\Stream'
            ]
        ];
        $config = Set::merge($defaults, $config);

        parent::__construct($config);

        $this->_version = $config['version'];
        $this->_scheme = $config['scheme'];

        if ($config['type']) {
            $this->type($config['type']);
        } elseif (isset($this->_headers['content-type'])) {
            $this->type($this->_headers['content-type']);
        }
    }

    public function protocol()
    {
        return "HTTP/" . $this->version();
    }

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
     * @param  string $type A full Content-Type i.e. `'application/json'`.
     * @return string       The Content-Type.
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

        return $this->_headers['content-type'] = $type;
    }

    /**
     * Gets/sets the string body of the message body.
     *
     * @param  array  $options
     * @return string
     */
    public function body($options = [])
    {
        if (func_num_args() === 0) {
            return $this->_body;
        }

        $default =[
            'encode' => false,
            'decode' => false
        ];
        $options += $default;

        $body = $this->_body;

        if ($options['encode']) {
            $body = $this->_encode($body);
        }
        if ($options['decode']) {
            $body = $this->_decode($body);
        }
        return $body;
    }

    /**
     * Encode the body based on the content type.
     *
     * @see    net\http\Message::type()
     * @param  mixed $body
     * @return string
     */
    protected function _encode($body)
    {
        $media = $this->_classes['media'];
        return $media::encode($this->type(), $body) ?: $body;
    }

    /**
     * Decode the body based on the content type.
     *
     * @see    net\http\Message::type()
     * @param  string $body
     * @return mixed
     */
    protected function _decode($body)
    {
        $media = $this->_classes['media'];
        return $media::decode($this->type(), $body) ?: $body;
    }
}

?>