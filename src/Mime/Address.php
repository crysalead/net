<?php
namespace Lead\Net\Mime;

use InvalidArgumentException;

/**
 * Email/name address container (support UTF-8 charset only).
 */
class Address
{
    /**
     * Email address
     *
     * @var string
     */
    protected $_email;

    /**
     * Name
     *
     * @var string
     */
    protected $_name;

    /**
     * Constructor
     *
     * @param  string      $address An email address or a simple email string
     * @param  null|string $name    An optionnal name
     */
    public function __construct($address, $name = null)
    {
        if (!$address || !is_string($address)) {
            throw new InvalidArgumentException("Email can't be empty.");
        }

        if (!$name && preg_match('~^(.+) +<(.*)>\z~', $address, $matches)) {
            $email = $matches[2];
            $name = $matches[1];
        } else {
            $email = $address;
        }

        $email = trim(str_replace(["\r", "\n"], '', $email));

        if (!static::isValid(Mime::encodeEmail($email))) {
            throw new InvalidArgumentException("Invalid email `'{$address}'`, can't be parsed.");
        }

        $this->_email = $email;
        $name = trim(str_replace(["\r", "\n"], '', $name));

        if (!$name) {
            return;
        }

        if (!is_string($name)) {
            throw new InvalidArgumentException('Name must be a string');
        }

        $this->_name = $name;
    }

    /**
     * Retrieve email
     *
     * @return string
     */
    public function email()
    {
        return $this->_email;
    }

    /**
     * Retrieve name
     *
     * @return string
     */
    public function name()
    {
        return $this->_name;
    }

    /**
     * Return the encoded representation of the address
     *
     * @return string
     */
    public function toString()
    {
        $email = '<' . Mime::encodeEmail($this->email()) . '>';
        $name = $this->name();
        return $name ? Mime::encodeValue($name) . ' ' . $email : $email;
    }

    /**
     * Return the encoded representation of the address
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Check if an email is valid.
     *
     * Uses the same RFC5322 regex on which FILTER_VALIDATE_EMAIL is based, but allows dotless domains.
     * @link http://squiloople.com/2009/12/20/email-address-validation/
     * @copyright 2009-2010 Michael Rushton
     * Feel free to use and redistribute this code. But please keep this copyright notice.
     *
     * @param  string $text The text
     * @return string
     */
    public static function isValid($email)
    {
        return (boolean) preg_match(
            '/^(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){255,})(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){65,}@)' .
            '((?>(?>(?>((?>(?>(?>\x0D\x0A)?[\t ])+|(?>[\t ]*\x0D\x0A)?[\t ]+)?)(\((?>(?2)' .
            '(?>[\x01-\x08\x0B\x0C\x0E-\'*-\[\]-\x7F]|\\\[\x00-\x7F]|(?3)))*(?2)\)))+(?2))|(?2))?)' .
            '([!#-\'*+\/-9=?^-~-]+|"(?>(?2)(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\x7F]))*' .
            '(?2)")(?>(?1)\.(?1)(?4))*(?1)@(?!(?1)[a-z0-9-]{64,})(?1)(?>([a-z0-9](?>[a-z0-9-]*[a-z0-9])?)' .
            '(?>(?1)\.(?!(?1)[a-z0-9-]{64,})(?1)(?5)){0,126}|\[(?:(?>IPv6:(?>([a-f0-9]{1,4})(?>:(?6)){7}' .
            '|(?!(?:.*[a-f0-9][:\]]){8,})((?6)(?>:(?6)){0,6})?::(?7)?))|(?>(?>IPv6:(?>(?6)(?>:(?6)){5}:' .
            '|(?!(?:.*[a-f0-9]:){6,})(?8)?::(?>((?6)(?>:(?6)){0,4}):)?))?(25[0-5]|2[0-4][0-9]|1[0-9]{2}' .
            '|[1-9]?[0-9])(?>\.(?9)){3}))\])(?1)$/isD',
            $email
        );
    }
}
