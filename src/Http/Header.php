<?php
namespace Lead\Net\Http;

use RuntimeException;

/**
 * HTTP Header.
 */
class Header extends \Lead\Net\Field
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [
        'array'  => 'Lead\Collection\Collection::toArray',
        'header' => 'Lead\Net\Http\Header::toHeader'
    ];

    /**
     * Returns the headers as a string.
     *
     * @return string
     */
    public static function toHeader($collection)
    {
        $header = $collection->name() . ': ' . $collection->value();

        if (preg_match('~^(.{7998,})~m', $header)) {
            throw new RuntimeException("A header line with more that 8000 characters has been detected.");
        }
        return $header;
    }
}
