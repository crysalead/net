<?php
namespace Lead\Net\Mime;

use RuntimeException;
use InvalidArgumentException;

/**
 * Support class for MultiPart Mime Messages
 */
abstract class Mime
{
    /**
     * Return the best suitable encoding.
     *
     * @param  string $body The body
     * @return string
     */
    public static function optimalEncoding($body)
    {
        if (!preg_match('~[^\x00-\x7F]~', $body)) {
            return '7bit';
        }
        if (preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $body) > (strlen($body) / 3)) {
            return 'base64';
        }
        return 'quoted-printable';
    }

    /**
     * Return the best suitable charset.
     *
     * @param  string $body The body
     * @return string
     */
    public static function optimalCharset($body)
    {
        if (!preg_match('~[^\x00-\x7F]~', $body)) {
            return 'US-ASCII';
        }
        return 'UTF-8';
    }

    /**
     * Encoding method.
     *
     * Note: Not relying on `stream_filter_append()` since not stable.
     *
     * @param  string $body      The message to encode.
     * @param  string $encoding  The encoding.
     * @param  string $wrapWidth The wrap width.
     * @param  string $le        The wrap width line ending.
     * @return string
     */
    public static function encode($body, $encoding, $wrapWidth = 76, $le = "\r\n", $cut = false)
    {
        $encoding = strtolower($encoding);
        switch ($encoding) {
            case 'quoted-printable':
                $body = quoted_printable_encode($body);
                return $wrapWidth ? rtrim(chunk_split($body, $wrapWidth, $le)) : $body;
                break;
            case 'base64':
                $body = base64_encode($body);
                return $wrapWidth ? rtrim(chunk_split($body, $wrapWidth, $le)) : $body;
                break;
            case '7bit':
                if (preg_match('~[^\x00-\x7F]~', $body)) {
                    throw new RuntimeException("Can't use `'{$encoding}'` encoding, non 7 bit characters detected.");
                }
            case '8bit':
            case 'binary':
                return $wrapWidth ? wordwrap($body, $wrapWidth, $le, $cut) : $body;
                break;
            default:
                throw new InvalidArgumentException("Unsupported encoding `'{$encoding}'`.");
                break;
        }
    }

    /**
     * Punycode email address to its ASCII form, also known as punycode.
     *
     * @param  string      $email The email address to encode
     * @return string|null        The encoded address in ASCII form or `null` on error.
     */
    public static function encodeEmail($email)
    {
        if (($pos = strpos($email, '@')) === false) {
            return;
        }
        if (!preg_match('~[\x80-\xFF]~', $email)) {
            return $email;
        }
        $domain = substr($email, ++$pos);
        return substr($email, 0, $pos) . idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * MIME-encode a value to its mots suitable format
     *
     * @param  string $value The value to encode.
     * @return string        Returns the encoded value.
     */
    public static function encodeValue($value, $wrapWidth = 998, $folding = "\r\n ")
    {
        $encoding = Mime::optimalEncoding($value);
        $encodedName = Mime::encode($value, $encoding, $wrapWidth, $folding, true);

        $value = trim(str_replace(["\r", "\n"], '', $value));

        if ($encoding === 'base64') {
            $encodedName = "=?UTF-8?B?{$encodedName}?=";
        } elseif ($encoding === 'quoted-printable') {
            $encodedName = "=?UTF-8?Q?{$encodedName}?=";
        } else {
            $encodedName = addcslashes($encodedName, "\0..\37\177\\\"");
            if ($encodedName !== $value || preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $value) === 1) {
                $encodedName = sprintf('"%s"', $encodedName);
            }
        }
        return $encodedName;
    }

    /**
     * MIME-decode a value
     *
     * @param  string $value The value to decode.
     * @return string        Returns the decoded value.
     */
    public static function decodeValue($value)
    {
        return iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    }

}
