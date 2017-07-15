<?php
namespace Lead\Net\Mime\Header;

use Lead\Net\Mime\Address;

class Addresses extends \Lead\Net\Mime\Header
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'Lead\Collection\Collection::toArray',
        'header' => 'Lead\Net\Mime\Header\Addresses::toHeader'
    ];

    /**
     * Assigns a header.
     *
     * @param  string $name  The header name.
     * @param  string $address The header value.
     * @return object        The setted value.
     */
    public function offsetSet($name, $address)
    {
        if (!$address instanceof Address) {
            $address = new Address($address);
        }
        return $this->_data[$address->email()] = $address;
    }

    /**
     * Returns the headers as a string.
     *
     * @return string
     */
    public static function toHeader($collection)
    {
        $data = [];
        $header = $collection->name();
        $len = 0;
        $isFirstPass = true;
        foreach ($collection as $value) {
            $len += strlen($value);
            if ($isFirstPass) {
                $header .= ': ' . $value;
            } elseif ($len + 2 <= 76) {
                $header .= ', ' . $value;
            } else {
                $header .= ",\r\n " . $value;
                $len = 0;
            }
            $isFirstPass = false;
        }

        if (preg_match('~^(.{998,})~m', $header)) {
            throw new RuntimeException("Header line with more that 1000 characters are not allowed.");
        }
        return $header;
    }
}
