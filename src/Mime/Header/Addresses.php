<?php
namespace Lead\Net\Mime\Header;

use Lead\Net\Mime\Headers;
use Lead\Net\Mime\Address;

class Addresses extends \Lead\Net\Header
{
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
}
