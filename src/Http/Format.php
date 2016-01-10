<?php
namespace Lead\Net\Http;

/**
 * The `Format` class facilitates content negociation and data encoding/decoding.
 */
class Format
{
    /**
     * Format definitions.
     *
     * @var array
     */
    protected static $_formats = [];

    /**
     * Sets a format definition.
     *
     * Example of a CSV format definition.
     *
     * ```php
     *  Media::set('csv', [
     *      'type'   => ['application/csv'],
     *      'encode' => function($data) {
     *          ob_start();
     *          $out = fopen('php://output', 'w');
     *          foreach ($data as $record) {
     *              fputcsv($out, $record);
     *          }
     *          fclose($out);
     *          return ob_get_clean();
     *      },
     *      'decode' => function($data) {
     *          $lines = explode(PHP_EOL, $data);
     *          $array = [];
     *          foreach ($lines as $line) {
     *              $array[] = str_getcsv($line);
     *          }
     *          return $array;
     *      }
     *  ]);
     *
     * @param  string $format     The format to register.
     * @param  array  $definition The definition array.
     */
    public static function set($format, $definition = [])
    {
        if ($definition === false) {
            unset(static::$_formats[$type]);
        }

        if (func_num_args() === 1) {
            foreach ($format as $key => $value) {
                static::set($key, ['type' => $value]);
            }
            return;
        }

        $definition += [
            'type'       => ['text/html'],
            'decode'     => null,
            'encode'     => null,
            'conditions' => []
        ];
        $definition['type'] = (array) $definition['type'];

        static::$_formats[$format] = $definition;
    }

    /**
     * Gets a format definition.
     *
     * @param  string     $format The format name.
     * @return array|null         The format definition array or `null` if not found.
     */
    public static function get($format)
    {
        return isset(static::$_formats[$format]) ? static::$_formats[$format] : null;
    }

    /**
     * Gets the main Content-Type attached to a format.
     *
     * @param  string      $format A format name.
     * @return string|null         A Content-Type or `null` if the format doesn't exists
     */
    public static function type($format)
    {
        $definition = static::get($format);

        if ($definition) {
            return reset($definition['type']);
        }
    }

    /**
     * Alias for `encode()` included for interface compatibility with `Lead\Collection\Collection::to()`,
     * which allows a collection object to be exported to any format supported by a `Format` handler.
     *
     * @param  mixed $format  Format into which data will be converted, i.e. `'json'`.
     * @param  mixed $data    Either an array or object (usually an instance of `Collection`) which will
     *                        be converted into the specified format.
     * @param  array $options Additional handler-specific options to pass to the content handler.
     * @return mixed
     */
    public static function to($format, $data, $options = [])
    {
        return static::encode($format, $data, $options);
    }

    /**
     * Performs Content-Type negotiation on a `Request` object, by iterating over the accepted
     * types in sequence, from most preferred to least, and attempting to match a format
     * defined by `Format::set()`.
     *
     * @param  object $request A request instance .
     * @return string          Returns the first matching format, i.e. `'html'` or `'json'`.
     */
    public static function negotiate($request)
    {
        foreach ($request->accepts() as $type) {
            if ($format = static::suitable($type, $request)) {
                return $format;
            }
        }
        return static::suitable($request->type(), $request);
    }

    /**
     * Iterates through all existing format to match the one compatible to the provided content type and request.
     *
     * @param  string  $type    A content type.
     * @param  object  $request An instance of request.
     * @return boolean          Returns a compatible format name or `null` if none matched.
     */
    public static function suitable($type, $request)
    {
        $formats = static::$_formats;

        foreach ($formats as $format => $definition) {
            if (!static::_match($type, $definition, $request)) {
                continue;
            }
            return $format;
        }
    }

    /**
     * Checks if a request is matchable with specific format.
     *
     * @param  string  $format  The format to match.
     * @param  object  $request An instance of request.
     * @return boolean          Returns `true` if the request matches the format, `false` otherwise.
     */
    public static function match($format, $request)
    {
        if (!$definition = static::get($format)) {
            return false;
        }
        return static::_match($request->type(), $definition, $request);
    }

    /**
     * Helper for `suitable()` && `match`.
     *
     * @param  string  $type       A content type.
     * @param  string  $definition A format definition.
     * @param  object  $request    An instance of request.
     * @return boolean             Returns `true` if the request matches the format definition, `false` otherwise.
     */
    public static function _match($type, $definition, $request)
    {
        if (!in_array($type, $definition['type'], true)) {
            return false;
        }
        foreach ($definition['conditions'] as $key => $value) {
            switch (true) {
                case strpos($key, ':'):
                    if ($request->get($key) !== $value) {
                        return false;
                    }
                break;
                case ($request->is($key) !== $value):
                    return false;
                break;
            }
        }
        return true;
    }

    /**
     * Encodes data according to the specified media format.
     *
     * @param  string $format  The media format into which `$data` will be encoded.
     * @param  mixed  $data    Arbitrary data you wish to encode.
     * @param  array  $options Handler-specific options.
     * @return mixed           The encoded data.
     */
    public static function encode($format, $data, $options = [])
    {
        $definition = static::get($format);

        if (empty($definition['encode'])) {
            return $data;
        }

        $cast = function($data) {
            if (!is_object($data)) {
                return $data;
            }
            return method_exists($data, 'to') ? $data->to('array') : get_object_vars($data);
        };

        if (!empty($handler['cast'])) {
            $data = is_object($data) ? $cast($data) : $data;
        }

        $handler = $definition['encode'];
        return is_string($handler) ? $handler($data) : $handler($data, $definition + $options);
    }

    /**
     * Decodes data according to the specified media format.
     *
     * @param  string $format  The media format into which `$data` will be decoded.
     * @param  mixed  $data    Arbitrary data you wish to decode.
     * @param  array  $options Handler-specific options.
     * @return mixed           The decoded data.
     */
    public static function decode($format, $data, $options = [])
    {
        $definition = static::get($format);

        if (empty($definition['decode'])) {
            return $data;
        }
        $handler = $definition['decode'];
        return is_string($handler) ? $handler($data) : $handler($data, $definition + $options);
    }

    /**
     * Resets the `Media` class to its default state.
     */
    public static function reset()
    {
        static::$_formats = [];

        static::set([
            'html' => ['text/html', 'application/xhtml+xml'],
            'form' => ['application/x-www-form-urlencoded', 'multipart/form-data'],
            'json' => ['application/json'],
            'rss'  => ['application/rss+xml'],
            'atom' => ['application/atom+xml'],
            'css'  => ['text/css'],
            'js'   => ['application/javascript', 'text/javascript'],
            'text' => ['text/plain'],
            'xml'  => ['application/xml', 'application/soap+xml', 'text/xml']
        ]);

        static::set('json', [
            'type'   => ['application/json', 'application/x-json'],
            'encode' => 'json_encode',
            'decode' => function($data) {
                if ($data === '') {
                    return '""';
                }
                return json_decode($data, true);
            }
        ]);

        static::set('form', [
            'type'   => ['application/x-www-form-urlencoded', 'multipart/form-data'],
            'encode' => 'http_build_query',
            'decode' => function($data) {
                $decoded = array();
                parse_str($data, $decoded);
                return $decoded;
            }
        ]);
    }
}

Format::reset();
