<?php
namespace Lead\Net\Mime\Stream;

use RuntimeException;
use InvalidArgumentException;
use Lead\Storage\Stream\Stream;
use Lead\Storage\Stream\MultiStream;

class MimeStream extends MultiStream
{
    /**
     * The multipart mime value
     *
     * @var string
     */
    protected $_mime = null;

    /**
     * The multipart boundary value
     *
     * @var string
     */
    protected $_boundary = null;

    /**
     * The constructor
     *
     * @param array $config The configuration array. Possibles values are:
     *                      -`boundary` _string_ : an optional boundary value.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'mime'     => null,
            'boundary' => null
        ];
        $config += $defaults;

        $this->mime($config['mime']);
        $this->boundary(isset($config['boundary']) ? $config['boundary'] : sha1(uniqid('', true)));
        parent::__construct($config);
    }

    /**
     * Get/set the boundary
     *
     * @param  string $boundary The boundary
     * @return string
     */
    public function boundary($boundary = null)
    {
        if (!func_num_args()) {
            return $this->_boundary;
        }
        $this->_boundary = $boundary;
        return $this;
    }

    /**
     * Get/set the stream mime.
     *
     * @param  mixed  $mime The mime string to set or `true` to autodetect the mime.
     * @return string       The mime.
     */
    public function mime($mime = null)
    {
        if (!func_num_args()) {
            if (!$this->_mime && $this->isMultipart()) {
                return 'multipart/form-data';
            }
            return $this->_mime;
        }
        $this->_mime = $mime;
        return $this;
    }

    /**
     * Check is it's a multipart string.
     *
     * @return boolean
     */
    public function isMultipart()
    {
        return count($this->_streams) > 1 || preg_match('~^multipart/~i', $this->_mime);
    }
    /**
     * Add a stream
     *
     * @param object $stream Stream to append.
     *
     * @throws InvalidArgumentException if the stream is not readable
     */
    public function add($stream, $options = [])
    {
        $defaults = [
            'id'          => null,
            'name'        => null,
            'filename'    => null,
            'description' => null,
            'location'    => null,
            'language'    => null,
            'length'      => false,
            'disposition' => null,
            'mime'        => true,
            'encoding'    => null,
            'charset'     => null,
            'headers'     => []
        ];

        $options += $defaults;

        $encoding = $options['encoding'];
        $mime = $options['mime'];
        $charset = $options['charset'];

        foreach (['mime', 'charset', 'encoding'] as $name) {
            unset($options[$name]);
        }

        if ($stream instanceof PartStream) {
            foreach (['mime', 'charset', 'encoding'] as $name) {
                if (!empty(${$name})) {
                    $stream->{$name}(${$name});
                }
            }
            $stream->options($options);
        } elseif (is_scalar($stream)) {
            $stream = new PartStream([
                'data'     => (string) $stream,
                'mime'     => $mime,
                'charset'  => $charset,
                'encoding' => $encoding,
                'options'  => $options
            ]);
        }

        if (isset($options['disposition']) && !isset($options['name'])) {
            throw new InvalidArgumentException("The `'name'` option is required.");
        }

        parent::add($stream);

        return $stream;
    }

    /**
     * Return the remaining data from the stream.
     *
     * @return string
     */
    public function flush()
    {
        $buffer = '';
        $boundary = $this->boundary();
        if (!$this->isMultipart()) {
            $stream = reset($this->_streams);
            return $stream ? $stream->toString() : '';
        }
        foreach ($this->_streams as $stream) {
            if ($stream instanceof static) {
                $buffer .= $stream->toString();
                continue;
            }
            $buffer .= '--' . $boundary . "\r\n";

            $mime = $stream->mime();
            $charset = $stream->charset();
            $options = $stream->options();

            if ($mime && !$charset && preg_match('~^text/~', $mime)) {
                $charset = 'utf-8';
            }

            if ($mime && !$stream->encoding()) {
                $stream->encoding(preg_match('~^text/~', $mime) ? 'quoted-printable' : 'base64');
            }

            $content = (string) $stream;
            $headers = $this->_headers($options, $mime, $charset, $stream->encoding(), strlen($content));
            $buffer .= join("\r\n", $headers) . "\r\n\r\n";
            $buffer .= $content . "\r\n";
        }
        return $buffer . '--' . $this->boundary() . "--\r\n";
    }

    /**
     * Extract headers form streams options.
     *
     * @param  array  $options The stream end user options.
     * @param  string $length  The length of the encoded stream.
     * @return array
     */
    protected function _headers($options, $mime, $charset, $encoding, $length)
    {
        $headers = !empty($options['headers']) ? $options['headers'] : [];

        if (!empty($options['disposition'])) {
            $parts = [$options['disposition'], "name=\"{$options['name']}\""];
            if (!empty($options['filename'])) {
                $parts[] = "filename=\"{$options['filename']}\"";
            }
            $headers[] = "Content-Disposition: " . join('; ', $parts);
        }

        if (!empty($options['id'])) {
            $headers[] = 'Content-ID: ' . $options['id'];
        }

        if (!empty($mime)) {
            $charset = $charset ? '; charset=' . $charset : '';
            $headers[] = 'Content-Type: ' . $mime . $charset;
        }

        if (!empty($encoding)) {
            $headers[] = 'Content-Transfer-Encoding: ' . $encoding;
        }

        if (!empty($options['length'])) {
            $headers[] = 'Content-Length: ' . $length;
        }

        if (!empty($options['description'])) {
            $headers[] = 'Content-Description: ' . $options['description'];
        }

        if (!empty($options['location'])) {
            $headers[] = 'Content-Location: ' . $options['location'];
        }

        if (!empty($options['language'])) {
            $headers[] = 'Content-Language: ' . $options['language'];
        }
        return $headers;
    }
}
