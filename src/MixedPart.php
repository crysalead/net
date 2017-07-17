<?php
namespace Lead\Net;

use RuntimeException;
use InvalidArgumentException;
use Lead\Set\Set;
use Lead\Net\Mime\Mime;

/**
 * Support Multipart message with HTTP headers as well as single part message.
 */
class MixedPart extends \Lead\Storage\Stream\MultiStream
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * The headers instance.
     *
     * @var object
     */
    protected $_headers = null;

    /**
     * The multipart mime value
     *
     * @var string
     */
    protected $_mime = null;

    /**
     * The charset info.
     *
     * @var string
     */
    protected $_charset = null;

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
            'charset'  => null,
            'boundary' => null,
            'headers'  => null,
            'classes'  => [
                'part'    => 'Lead\Net\Part'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $config['headers'] = is_object($config['headers']) ?  $config['headers'] : new Headers(['data' => $config['headers']]);
        $this->headers($config['headers']);

        parent::__construct($config);

        $this->_initContentType($config['mime'], $config['charset']);
        $this->boundary($config['boundary']);
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
            if (!$this->isMultipart()) {
                return;
            }
            if ($this->_boundary === null) {
                $this->_boundary = sha1(uniqid('', true));
            }
            return $this->_boundary;
        }
        $this->_boundary = $boundary;
        return $this;
    }

    /**
     * Get/set the headers instance
     *
     * @param  string $headers The headers instance
     * @return string
     */
    public function headers($headers = null)
    {
        if (!func_num_args()) {
            return $this->_headers;
        }
        $this->_headers = $headers;
        return $this;
    }

    /**
     * Gets/sets the Content-Type.
     *
     * @param  string      $mime A full Content-Type i.e. `'application/json'`.
     * @return string|self
     */
    public function mime($mime = null)
    {
        if (!func_num_args()) {
            if (!$this->isMultipart()) {
                list($mime, $charset) = $this->_inspectContentType();
                return $this->_mime = $mime;
            }
            return preg_match('~^multipart/~', $this->_mime) ? $this->_mime : 'multipart/form-data';
        }

        unset($this->_headers['Content-Type']);
        if ($this->_mime = $mime) {
            $charset = $this->charset();
            $this->_headers['Content-Type'] = $mime . ($charset ? '; charset=' . $charset : '');
        } else {
            $this->_mime = null;
        }
        return $this;
    }

    /**
     * Gets/sets the Content-Type charset.
     *
     * @param  string      $charset A charset i.e. `'UTF-8'`.
     * @return string|self
     */
    public function charset($charset = null)
    {
        if (!func_num_args()) {
            return $this->_charset;
        }
        $mime = $this->mime();
        unset($this->_headers['Content-Type']);
        if ($charset) {
            $this->_charset = $charset ? strtoupper($charset) : null;
            if ($mime) {
                $this->_headers['Content-Type'] = $mime . ($this->_charset ? '; charset=' . $this->_charset : '');
            }
        } elseif ($mime) {
            $this->_charset = null;
            $this->_headers['Content-Type'] = $mime;
        }
        return $this;
    }

    /**
     * Init default mime/charset values.
     *
     * @param  string      $mime    A mime or `null` to use the default one.
     * @param  string      $charset A charset or `null` to use the default one.
     */
    protected function _initContentType($mime, $charset)
    {
        if (!isset($this->_headers['Content-Type'])) {
            $this->mime($mime);
            $this->charset($charset);
            return;
        }

        list($mime, $charset) = $this->_inspectContentType();
        unset($this->_headers['Content-Type']);

        $this->mime($mime);
        $this->charset($charset);
    }

    /**
     * Extract the Content Type (mime + charset) from headers
     *
     * @return array
     */
    protected function _inspectContentType()
    {
        $mime = null;
        $charset = null;
        if (preg_match('/([-\w\/\.+]+)(;\s*?charset=(.+))?/i', $this->_headers['Content-Type']->value(), $matches)) {
            $mime = $mime ?: $matches[1];
            if (isset($matches[3])) {
                $charset = $charset ?: $matches[3];
            }
        }
        return [$mime, $charset];
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

        $part = $this->_classes['part'];

        if ($stream instanceof $part) {
            foreach (['mime', 'charset', 'encoding'] as $name) {
                if (!empty(${$name})) {
                    $stream->{$name}(${$name});
                }
            }
            $stream->options($options);
        } elseif (is_scalar($stream)) {
            $stream = new $part([
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
        if (!$this->isMultipart()) {
            $stream = reset($this->_streams);
            return ($stream ? $stream->toString() : '');
        }

        $buffer = '';
        $boundary = $this->boundary();

        foreach ($this->_streams as $stream) {
            if ($stream instanceof static) {
                $buffer .= $stream->toString();
                continue;
            }
            $buffer .= '--' . $boundary . "\r\n";

            $mime = $stream->mime();
            $charset = $stream->charset();
            $options = $stream->options();

            if ($mime && !$stream->encoding()) {
                $stream->encoding(preg_match('~^text/~', $mime) ? 'quoted-printable' : 'base64');
            }
            $content = $stream->toString();

            if ($mime && !$charset && preg_match('~^text/~', $mime)) {
                $charset = Mime::optimalCharset($content);
            }

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

    /**
     * Magic method to convert the instance into an HTTP message string.
     *
     * @return string
     */
    public function toMessage()
    {
        $headers = $this->_headers;
        if ($this->isMultipart()) {
            $boundary = $this->boundary();
            $mime = $this->mime();
            unset($this->_headers['Content-Type']);
            $this->_headers['Content-Type'] = "{$mime}; boundary={$boundary}";
        }
        return $headers->toString() . "\r\n" . $this->toString();
    }

    /**
     * Clones each streams.
     */
    public function __clone()
    {
        parent::__clone();
        $this->_headers = clone $this->_headers;
    }
}
