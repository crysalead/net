<?php
namespace Lead\Net\Http;

/**
 * The `Media` class facilitates Content-Type mapping.
 */
class Media
{
    /**
     * Maps file extensions to content-types.  Used to set response types and determine request
     * types. Can be modified with `Media::type()`.
     *
     * @var array
     */
    protected static $_types = [];

    /**
     * A map of media handler objects or callbacks, mapped to media types.
     *
     * @var array
     */
    protected static $_handlers = [];

    /**
     * Returns the list of registered media types.  New types can be set with the `type()` method.
     *
     * @return array Returns an array of media type extensions or short-names, which comprise the
     *               list of types handled.
     */
    public static function types()
    {
        return array_keys(static::$_handlers);
    }

    /**
     * Alias for `types()`.
     *
     * @return array Returns the value of `Media::types()`.
     */
    public static function formats()
    {
        return static::types();
    }

    /**
     * Alias for `encode()`; included for interface compatibility with
     * `Lead\Collection\Collection::to()`, which allows a collection object to be exported to any
     * format supported by a `Media` handler.
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
     * Maps a type name to a particular content-type with a set of options, or
     * retrieves information about a type that has been defined.
     *
     * @param string $type    The Content-Type to register.
     * @param array  $options The handling options for this media type.
     * @return mixed          If `$options` is empty, returns an array with Content-Types and
     *                        their definirtion. If `$options` is non-empty, returns `null`.
     */
    public static function type($type, $options = [])
    {
        if (func_num_args() === 1) {
            return isset(static::$_handlers[$type]) ? static::$_handlers[$type] : [];
        }
        if ($options === false) {
            unset(static::$_handlers[$type]);
        }
        static::$_handlers[$type] = $options;
    }

    /**
     * For media types registered in `$_handlers` which include an `'encode'` setting, encodes data
     * according to the specified media type.
     *
     * @param  mixed  $handler  Specifies the media type into which `$data` will be encoded. This media
     *                          type must have an `'encode'` setting specified in `Media::$_handlers`.
     *                          Alternatively, `$type` can be an array, in which case it is used as the type
     *                          handler configuration. See the `type()` method for information on adding type
     *                          handlers, and the available configuration keys.
     * @param  mixed  $data     Arbitrary data you wish to encode. Note that some encoders can only handle
     *                          arrays or objects.
     * @param  object $response A reference to the `Response` object for this dispatch cycle.
     * @return mixed            Returns the result of `$data`, encoded with the encoding configuration
     *                          specified by `$type`, the result of which is usually a string.
     */
    public static function encode($handler, $data, $options = [])
    {
        $handler = is_array($handler) ? $handler : static::handlers($handler);

        if (!$handler || empty($handler['encode'])) {
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
        $method = $handler['encode'];
        return is_string($method) ? $method($data) : $method($data, $handler + $options);
    }

    /**
     * For media types registered in `$_handlers` which include an `'decode'` setting, decodes data
     * according to the specified media type.
     *
     * @param  string $type    Specifies the media type into which `$data` will be encoded. This media
     *                         type must have an `'encode'` setting specified in `Media::$_handlers`.
     * @param  mixed  $data    Arbitrary data you wish to encode. Note that some encoders can only handle
     *                         arrays or objects.
     * @param  array  $options Handler-specific options.
     * @return mixed
     */
    public static function decode($type, $data, $options = [])
    {
        if ((!$handler = static::handlers($type)) || empty($handler['decode'])) {
            return $data;
        }
        $method = $handler['decode'];
        return is_string($method) ? $method($data) : $method($data, $handler + $options);
    }

    /**
     * Helper method for listing registered type handlers. Returns all handlers, or the
     * handler for a specific media type, if requested.
     *
     * @param  string $type  The type of handler to return.
     * @return mixed         Array of all handlers, or the handler for a specific type.
     */
    public static function handlers($type = null)
    {
        $handlers = static::$_handlers + [
            'default' => [
                'cast' => true,
                'decode' => function($s) {
                    return (string) $s;
                },
                'encode' => function($s) {
                    return (string) $s;
                }
            ],
            'application/json' => [
                'encode' => 'json_encode',
                'decode' => function($data) {
                    if ($data === '') {
                        return '""';
                    }
                    return json_decode($data, true);
                }
            ],
            'application/x-www-form-urlencoded' => [
                'encode' => 'http_build_query',
                'decode' => function($data) {
                    $decoded = array();
                    parse_str($data, $decoded);
                    return $decoded;
                }
            ]
        ];

        if ($type) {
            return isset($handlers[$type]) ? $handlers[$type] + $handlers['default'] : null;
        }
        return $handlers;
    }

    /**
     * Resets the `Media` class to its default state. Mainly used for ensuring a consistent state during testing.
     */
    public static function reset()
    {
        static::$_handlers = [];
        static::$_types = [];
    }
}
