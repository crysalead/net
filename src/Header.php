<?php
namespace Lead\Net;

use RuntimeException;

/**
 * Generic colon based header.
 */
class Header extends \Lead\Collection\Collection
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'Lead\Collection\Collection::toArray',
        'header' => 'Lead\Net\Header::toHeader'
    ];

    /**
     * The header name.
     *
     * @var string
     */
    protected $_name = '';

    /**
     * The plain header value.
     *
     * @var string
     */
    protected $_plain = '';

    /**
     * The constructor
     *
     * @param string $name  The header name
     * @param array  $value The header value
     */
    public function __construct($name = '', $value = '')
    {
        if (func_num_args() === 1) {
            $value = $name;
            $name = '';
        }
        $value = is_array($value) ? join(',', $value) : $value;
        $value = htmlspecialchars_decode(htmlspecialchars($value, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES);
        $value = trim(str_replace(["\r", "\n"], '', $value));

        $this->_name = $name;
        $this->_plain = $value;

        if (!empty($this->_plain)) {
            $data = array_map('trim', strtolower($name) === 'set-cookie' ? [$this->_plain] : explode(',', $this->_plain));
        } else {
            $data = [];
        }
        parent::__construct($data);
    }

    /**
     * Gets/sets the Header name.
     *
     * @param  string $name The name string.
     * @return string       Returns the name string.
     */
    public function name($name = null)
    {
        if (func_num_args() === 0) {
            return $this->_name;
        }
        return $this->_name = (string) $name;
    }

    /**
     * Assigns a header.
     *
     * @param  string $name  The header name.
     * @param  mixed  $value The header value.
     * @return mixed         The setted value.
     */
    public function offsetSet($name, $value)
    {
        if ($name && !is_numeric($name)) {
            throw new RuntimeException("Invalid index, should be numeric or empty.");
        }
        return $name === null ? $this->_data[] = $value : $this->_data[$name] = $value;
    }

    /**
     * Gets the plain header's value.
     *
     * @return array Returns the headers.
     */
    public function plain()
    {
        return $this->_plain;
    }

    /**
     * Gets the header's value.
     *
     * @return array Returns the headers.
     */
    public function value()
    {
        if (strtolower($this->name()) === 'set-cookie') {
            return join("\r\n" . $this->name() . ': ', $this->data());
        } else {
            return join(', ', $this->_data);
        }
    }

    /**
     * Exports the header as an array.
     *
     * @return array Returns the headers.
     */
    public function data()
    {
        return $this->_data;
    }

    /**
     * Returns the headers as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return static::toHeader($this);
    }

    /**
     * Parses a header string value.
     *
     * @param  string $header A header string value.
     * @return object         A header collection.
     */
    public static function parse($value)
    {
        $values = explode(':', $value, 2);
        if (count($values) !== 2) {
            return;
        }

        return new static($values[0], trim($values[1]));
    }

    /**
     * Returns the headers as a string.
     *
     * @return string
     */
    public static function toHeader($collection, $options = [])
    {
        $defaults = [
            'length'    => 0,
            'maxLength' => 0
        ];
        $options += $defaults;

        if (strtolower($collection->name()) === 'set-cookie') {
            $header = static::wrap($collection->name() . ': ' . join("\r\n" . $collection->name() . ': ', $collection->data()), $options['length']);
        } else {
            $header = static::wrap($collection->name() . ': ' . $collection->value(), $options['length']);
        }

        $maxLength = $options['maxLength'];
        if ($maxLength && preg_match('~^(.{' . $maxLength . ',})~m', $header)) {
            throw new RuntimeException("A header line with more that {$maxLength} characters has been detected.");
        }
        return $header;
    }

    /**
     * Fold a header entry
     *
     * @param string $header The header to fold.
     */
    public static function wrap($header, $width = 0)
    {
        if ($width <= 0) {
            return $header;
        }
        $result = [];
        $lineLength = 0;
        $parts = preg_split('~\s+~', $header);

        while ($current = current($parts))
        {
            $next = next($parts);
            $lineLength += strlen($current);
            if ($next && ($lineLength + strlen($next)) > ($width - 1)) {
                $result[] = $current . Headers::EOL;
                $lineLength = 0;
            } else {
                $result[] = $current;
            }
        }
        return join(' ', $result);
    }
}
