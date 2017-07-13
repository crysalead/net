<?php
namespace Lead\Net\Behavior;

trait HeadersTrait {

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Sets some headers.
     *
     * @param  string|array $headers A header name, string content or an array of headers.
     * @param  string|array $value   The header value. I set, `$headers` must be an header string name.
     * @return self
     */
    public function push($headers, $value = '')
    {
        if (is_string($headers) && func_num_args() === 2) {
            $headers = [$headers => $value];
        }
        return $this->_set($headers);
    }

    /**
     * Prepends some headers.
     *
     * @param  string|array $headers A header name, string content or an array of headers.
     * @param  string|array $value   The header value. I set, `$headers` must be an header string name.
     * @return self
     */
    public function prepend($headers, $value = '')
    {
        if (is_string($headers) && func_num_args() === 2) {
            $headers = [$headers => $value];
        }
        return $this->_set($headers, true);
    }

    /**
     * Assigns a header.
     *
     * @param  string $name  The header name.
     * @param  string $value The header value.
     * @return object        The setted value.
     */
    public function offsetSet($name, $value)
    {
        return $this->push($name ?: '', $value);
    }

    /**
     * Checks whether or not an header exists.
     *
     * @param  string  $name The header name.
     * @return boolean       Returns `true` if the header exists, `false` otherwise.
     */
    public function offsetExists($name)
    {
        $name = strtolower($name);
        if (!array_key_exists($name, $this->_data)) {
            return false;
        }
        return !empty($this->_data[$name]->value());
    }

    /**
     * Returns the value of a specific header.
     *
     * @param  string $name The header name.
     * @return string       The header value.
     */
    public function offsetGet($name)
    {
        if (!isset($this->_data[strtolower($name)])) {
            $header = $this->_classes['header'];
            return $this->_data[strtolower($name)] = new $header($name, '');
        }
        return $this->_data[strtolower($name)];
    }

    /**
     * Unsets an offset.
     *
     * @param string $offset The offset to unset.
     */
    public function offsetUnset($name)
    {
        return parent::offsetUnset(strtolower($name));
    }

    /**
     * Returns the key of the current item.
     *
     * @return scalar Scalar on success or `null` on failure.
     */
    public function key()
    {
        if ($header = current($this->_data)) {
            return $header->name();
        }
    }

    /**
     * Returns the current item.
     *
     * @return mixed The current item or `false` on failure.
     */
    public function valid()
    {
        if (key($this->_data) === null) {
            return false;
        }
        // Bails out all empty header on forEach.
        $current = current($this->_data);
        while (!$current->value()) {
            $current = $this->next();
            if (key($this->_data) === null) {
                return false;
            }
        }
        return true;
    }

    /**
     * Clear the headers.
     */
    public function clear()
    {
        $this->_data = [];
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
}