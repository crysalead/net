<?php
namespace net\http\cookie;

use Exception;

/**
 * Cookie value definition.
 */
class Cookie
{
    /**
     * Cookie data.
     *
     * @var string
     */
    protected $_data = [
        'value' => ''
    ];

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
     * Gets/sets the Cookie Value.
     *
     * @param  string $value The value.
     * @return string        Returns the value.
     */
    public function value($value = null)
    {
        if (func_num_args() === 0) {
            return $this->_data['value'];
        }
        $value = (string) $value;
        if (!$value && $value !== '0') {
            throw new Exception("Invalid cookie value `'{$value}'`.");
        }
        return $this->_data['value'] = $value;
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
