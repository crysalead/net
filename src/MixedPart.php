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

        $this->boundary($config['boundary']);
        $this->_initContentType($config['mime'], $config['charset']);
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
     * Get/set the Content-Type.
     *
     * @param  string      $mime A full Content-Type i.e. `'application/json'`.
     * @return string|self
     */
    public function mime($mime = null)
    {
        if (!func_num_args()) {
            list($mime, $charset) = $this->_inspectContentType();
            return $this->_mime = $mime;
        }

        $this->_mime = $mime ? strtolower($mime) : null;
        $this->_updateContentType();
        return $this;
    }

    /**
     * Get/set the Content-Type charset.
     *
     * @param  string      $charset A charset i.e. `'UTF-8'`.
     * @return string|self
     */
    public function charset($charset = null)
    {
        if (!func_num_args()) {
            list($mime, $charset) = $this->_inspectContentType();
            return $this->_charset = $charset;
        }
        $this->_charset = $charset ? strtoupper($charset) : null;
        $this->_updateContentType();
        return $this;
    }

    /**
     * Update Content-Type helper
     */
    public function _updateContentType()
    {
        unset($this->_headers['Content-Type']);
        $suffix = '';
        if ($this->isMultipart()) {
            $suffix = '; boundary=' . $this->boundary();
        } elseif ($this->_charset) {
            $suffix = '; charset=' . $this->_charset . $suffix;
        }
        if ($this->_mime) {
            $this->_headers['Content-Type'] = $this->_mime . $suffix;
        }
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
        } elseif ($mime) {
            $this->mime($mime);
        }
        list($mime, $charset) = $this->_inspectContentType();

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
        return preg_match('~^multipart/~i', $this->_mime);
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

        if (!$this->isMultipart() && !count($this->_streams)) {
            $mime = $this->mime() && ($mime === null || $mime === true) ? $this->mime() : $mime;
            $charset = $this->charset() && $charset === null ? $this->charset() : $charset;
        }

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
        } elseif (is_scalar($stream) || is_resource($stream)) {
            $stream = new $part([
                'data'     => $stream,
                'mime'     => $mime,
                'charset'  => $charset,
                'encoding' => $encoding,
                'options'  => $options
            ]);
        }

        if ($mime === 'multipart/form-data' && !isset($options['name'])) {
            throw new InvalidArgumentException("The `'name'` option is required.");
        }

        parent::add($stream);

        $this->syncContentType();

        return $stream;
    }

    /**
     * Remove a stream.
     *
     * @param  integer $index An index.
     * @return object         The removed stream.
     */
    public function remove($index)
    {
        $stream = parent::remove($index);
        $this->syncContentType();
        return $stream;
    }

    /**
     * Sync Content-Type helper.
     */
    public function syncContentType()
    {
        $stream = reset($this->_streams);
        if (!$this->isMultipart() && $stream) {
            unset($this->_headers['Content-Type']);
            $this->mime($stream->mime());
            $this->charset($stream->charset());
            unset($this->_headers['Content-Transfer-Encoding']);
            if ($encoding = $stream->encoding()) {
                $this->_headers['Content-Transfer-Encoding'] = $encoding;
            }
        }
    }

    /**
     * Return the remaining data from the stream.
     *
     * @return string
     */
    public function flush()
    {
        $buffer = '';
        if (!$this->isMultipart()) {
            foreach ($this->_streams as $stream) {
                $buffer .= $stream->toString();
            }
            return $buffer;
        }

        $boundary = $this->boundary();

        foreach ($this->_streams as $stream) {
            if ($stream instanceof static) {
                $buffer .= "\r\n--" . $boundary . "\r\n" . $stream->toMessage();
                continue;
            }
            $buffer .= "\r\n--" . $boundary . "\r\n";

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
            $buffer .= $content;
        }
        return $buffer . "\r\n--" . $this->boundary() . "--\r\n";
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
            $parts = [$options['disposition']];
            foreach (['name', 'filename'] as $name) {
                if (!empty($options[$name])) {
                    $value = htmlspecialchars_decode(htmlspecialchars($options[$name], ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES);
                    $value = preg_replace('~[\s/\\\]~', '', $value);
                    $value = addcslashes($value, '"');
                    $parts[] = "{$name}=\"{$value}\"";
                }
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
        return $this->_headers->toString() . "\r\n". "\r\n" . $this->toString();
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
