<?php
namespace Lead\Net;

/**
 * PhpInputStream
 *
 * This class is similar to `new Stream(['data' => fopen('php://input', 'r')])` except
 * that it support rewinding.
 */
class PhpInputStream extends \Lead\Storage\Stream\Stream
{
    /**
     * Gets stream meta data.
     *
     * @param  string $key A specific meta data or `null` to get all meta data.
     *                     Possibles values are:
     *                     `'uri'`          _string_ : the URI/filename associated with this stream.
     *                     `'mode'`         _string_ : the type of access required for this stream.
     *                     `'wrapper_type'` _string_ : the protocol wrapper implementation layered over the stream.
     *                     `'stream_type'`  _string_ : the underlying implementation of the stream.
     *                     `'unread_bytes'` _integer_: the number of bytes contained in the PHP's own internal buffer.
     *                     `'seekable'`     _boolean_: `true` means the current stream can be seeked.
     *                     `'eof'`          _boolean_: `true` means the stream has reached end-of-file.
     *                     `'blocked'`      _boolean_: `true` means the stream is in blocking IO mode.
     *                     `'timed_out'`    _boolean_: `true` means stream timed out on the last read call.
     * @return mixed
     */
    public function __construct($config = [])
    {
        $config['data'] = fopen('php://input', 'r');
        parent::__construct($config);
    }

    /**
     * Seeks on the stream.
     *
     * @param integer $offset The offset.
     * @param integer $whence Accepted values are:
     *                        - SEEK_SET - Set position equal to $offset bytes.
     *                        - SEEK_CUR - Set position to current location plus $offset.
     *                        - SEEK_END - Set position to end-of-file plus $offset.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if ($this->eof() && !$offset && $whence === SEEK_SET) {
            $this->close();
            $this->_resource = fopen('php://input', 'r');
        }
        return parent::seek($offset, $whence);
    }
}
