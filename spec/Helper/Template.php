<?php
namespace Lead\Net\Spec\Helper;

class Template {

    public static $patterns = [
        '%%' => '%',            // one % character
        '%a%' => '[^\r\n]+',    // one or more of anything except the end of line characters
        '%a\?%' => '[^\r\n]*',  // zero or more of anything except the end of line characters
        '%A%' => '.+',          // one or more of anything including the end of line characters
        '%A\?%' => '.*',        // zero or more of anything including the end of line characters
        '%s%' => '[\t ]+',      // one or more white space characters except the end of line characters
        '%s\?%' => '[\t ]*',    // zero or more white space characters except the end of line characters
        '%S%' => '\S+',         // one or more of characters except the white space
        '%S\?%' => '\S*',       // zero or more of characters except the white space
        '%c%' => '[^\r\n]',     // a single character of any sort (except the end of line)
        '%d%' => '[0-9]+',      // one or more digits
        '%d\?%' => '[0-9]*',    // zero or more digits
        '%i%' => '[+-]?[0-9]+', // signed integer value
        '%f%' => '[+-]?\.?\d+\.?\d*(?:[Ee][+-]?\d+)?', // floating point number
        '%h%' => '[0-9a-fA-F]+', // one or more HEX digits
        '%w%' => '[0-9a-zA-Z_]+', //one or more alphanumeric characters
        '%ds%' => '[\\\\/]',    // directory separator
        '%(\[.+\][+*?{},\d]*)%' => '$1', // range
    ];

    public static function pattern($pattern, $strict = false)
    {
        $utf8 = preg_match('#\x80-\x{10FFFF}]#u', $pattern) ? 'u' : '';
        $suffix = ($strict ? '\z#sU' : '\s*$#sU') . $utf8;
        $patterns = static::$patterns + [
            '[.\\\\+*?[^$(){|\#]' => '\$0', // preg quoting
            '\x00' => '\x00',
            '[\t ]*\r?\n' => '[\t ]*\r?\n', // right trim
        ];
        $pattern = '#^' . preg_replace_callback('#' . implode('|', array_keys($patterns)) . '#U' . $utf8, function ($m) use ($patterns) {
            foreach ($patterns as $re => $replacement) {
                $s = preg_replace("#^$re\\z#", str_replace('\\', '\\\\', $replacement), $m[0], 1, $count);
                if ($count) {
                    return $s;
                }
            }
        }, rtrim($pattern, " \t\n\r")) . $suffix;
        return $pattern;
    }

}