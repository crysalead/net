<?php
namespace Lead\Net\Http\Cookie;

use Exception;

class Cookie extends \Lead\Collection\Collection
{
    /**
     * Cookie data.
     *
     * @var string
     */
    protected $_data = [];

    /**
     * Constructor.
     *
     * @param array $config The Cookie data.
     */
    public function __construct($value, $config = [])
    {
        $this->value($value);
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
            throw new Exception("Invalid index, should be numeric or empty.");
        }
        if ($value instanceof static) {
            $value = $value->value();
        }
        return $this->_data[] = (string) $value;
    }

    /**
     * Gets/sets the Cookie Value.
     *
     * @param  string $value The value.
     * @return string        Returns the value.
     */
    public function value($value = null)
    {
        if (func_num_args() === 0) {
            return $this->_data[0];
        }
        $values = is_array($value) ? $value : [$value];
        $i = 0;
        foreach ($values as $key => $value) {
            if ($value instanceof static) {
                $value = $value->value();
            }
            $value = (string) $value;
            if (!$value && $value !== '0') {
                throw new Exception("Invalid cookie value `'{$value}'`.");
            }
            $this->_data[$i++] = (string) $value;
        }
        return reset($this->_data);
    }

    /**
     * Returns the Cookie data array.
     *
     * @return array
     */
    public function data()
    {
        return $this->_data;
    }

    /**
     * Checks if a cookie name is valid.
     *
     * @param  string  $name The name of the cookie.
     * @return boolean
     */
    public static function isValidName($name)
    {
        if (!$name || preg_match("/[=,; \t\r\n\013\014]/", $name)) {
            return false;
        }
        return true;
    }
}
