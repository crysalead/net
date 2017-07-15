<?php
namespace Lead\Net\Mime;

use RuntimeException;

/**
 * MIME Header.
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
        'header' => 'Lead\Net\Mime\Header::toHeader'
    ];

    /**
     * Returns the headers as a string.
     *
     * @return string
     */
    public static function toHeader($collection)
    {
        $header = $collection->name() . ': ' . $collection->value();

        if (preg_match('~^(.{' . MIME::MAX_LINE_LENGTH . ',})~m', $header)) {
            throw new RuntimeException('A header line with more that ' . MIME::MAX_LINE_LENGTH . ' characters has been detected.');
        }
        return $header;
    }
}
