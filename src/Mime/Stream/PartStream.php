<?php
namespace Lead\Net\Mime\Stream;

use RuntimeException;
use InvalidArgumentException;
use Lead\Net\Mime\Mime;

class PartStream extends \Lead\Storage\Stream\Stream
{
    /**
     * The encoding
     *
     * @var string
     */
    protected $_encoding = null;

    /**
     * The constructor
     *
     * @param array $config The configuration array. Possibles values are:
     *                      -`encoding` _string_  : The part encoding.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'encoding'   => false
        ];
        $config += $defaults;
        parent::__construct($config);
        $this->encoding($config['encoding']);
    }

    /**
     * Get/set the encoding.
     *
     * @param  string $encoding
     * @return string           The encoding.
     */
    public function encoding($encoding = null)
    {
        if (!func_num_args()) {
            return $this->_encoding;
        }
        $this->_encoding = $encoding;
        return $this;
    }

    /**
     * Throw an exception if a stream is not readable.
     *
     * @param boolean $bytePerByte Check if the stream is readable byte per byte.
     */
    protected function _ensureReadable($bytePerByte = true)
    {
        if ($bytePerByte && $this->_encoding) {
            throw new RuntimeException('Stream with encoding cannot be read byte per byte.');
        }
        parent::_ensureReadable();
    }

    /**
     * Return the remaining data from the stream.
     *
     * @param  string $encode Indicate if the returned data must ben encoded or not
     * @return string
     */
    public function flush($encode = true)
    {
        $this->_ensureReadable(false);
        $content = stream_get_contents($this->_resource);
        return $encode && $this->_encoding ? Mime::encode($content, $this->_encoding) : $content;
    }
}
