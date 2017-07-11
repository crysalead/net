<?php
namespace Lead\Net\Http;

use InvalidArgumentException;
use Lead\Net\NetException;
use Lead\Net\Http\Cgi\Request;

/**
 * The `Media` class sets encoding/decoding handlers for formats.
 */
class Media
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
     *  Media::set('csv', ['application/csv'], [
     *      'cast'   => true,
     *      'encode' => function($data, $options = []) {
     *          ob_start();
     *          $out = fopen('php://output', 'w');
     *          foreach ($data as $record) {
     *              fputcsv($out, $record);
     *          }
     *          fclose($out);
     *          return ob_get_clean();
     *      },
     *      'decode' => function($data, $options = []) {
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
    public static function set($format, $type, $definition = [])
    {
        $type = $type ? (array) $type : ['text/html'];

        $definition += [
            'cast'       => true,
            'decode'     => null,
            'encode'     => null,
            'type'       => $type,
            'conditions' => []
        ];

        static::$_formats[$format] = $definition;
    }

    /**
     * Removes a format.
     *
     * @param string|array $format The format to remove of an array of them.
     */
    public static function remove($format)
    {
        $formats = (array) $format;

        foreach ($formats as $format) {
            unset(static::$_formats[$format]);
        }
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
     * Iterates through all existing formats to match a compatible one for the provided request.
     *
     * @param  object  $message An instance of message.
     * @param  string  $type    An overriding content type.
     * @return boolean          Returns a compatible format name or `null` if none matched.
     */
    public static function suitable($message, $type = null)
    {
        $formats = static::$_formats;

        if (func_num_args() === 1) {
            $type = $message->type();
        }

        foreach ($formats as $format => $definition) {
            if (!in_array($type, $definition['type'], true)) {
                continue;
            }
            if ($message instanceof Request) {
                foreach ($definition['conditions'] as $key => $value) {
                    switch (true) {
                        case strpos($key, ':'):
                            if ($message->attr($key) !== $value) {
                                continue 2;
                            }
                        break;
                        case ($message->is($key) !== $value):
                            continue 2;
                        break;
                    }
                }
            }
            return $format;
        }
    }

    /**
     * Alias for `encode()` included for interface compatibility with `Lead\Collection\Collection::to()`,
     * which allows a collection object to be exported to any format supported by a `Media` handler.
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
     * Encodes data according to the specified format.
     *
     * @param  string $format  The format into which `$data` will be encoded.
     * @param  mixed  $data    Arbitrary data you wish to encode.
     * @param  array  $options Handler-specific options.
     * @return string          The encoded string data.
     */
    public static function encode($format, $data, $options = [])
    {
        $definition = static::get($format);

        if (empty($definition['encode'])) {
            if (is_string($data)) {
                return $data;
            }
            throw new NetException("The `$format` format requires data needs to be a string.");
        }

        $cast = function($data) {
            return method_exists($data, 'data') ? $data->data() : get_object_vars($data);
        };

        if (!empty($definition['cast'])) {
            $data = is_object($data) ? $cast($data) : $data;
        }

        $handler = $definition['encode'];
        return is_string($handler) ? $handler($data) : $handler($data, $definition + $options);
    }

    /**
     * Decodes data according to the specified format.
     *
     * @param  string $format  The format into which `$data` will be decoded.
     * @param  string $data    String data to decode.
     * @param  array  $options Handler-specific options.
     * @return mixed           The arbitrary decoded data.
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
     * Resets the `Media` class.
     */
    public static function reset()
    {
        static::$_formats = [];

        static::set('html', ['text/html', 'application/xhtml+xml']);
        static::set('xml', ['text/xml', 'application/xml']);
        static::set('text', ['text/plain']);

        static::set('json', ['application/json'], [
            'encode' => function($data, $options = []) {
                $defaults = [
                    'depth' => 512,
                    'flag'  => 0
                ];
                $options += $defaults;

                if (!empty($options['errors'])) {
                    $error = reset($options['errors']);
                    $data = [
                        'error' => [
                            'code' => $error->getCode(),
                            'title' => $error->getMessage(),
                            'trace' => explode("\n", $error->getTraceAsString())
                        ]
                    ];
                }

                $result = json_encode($data, $options['flag'], $options['depth']);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new InvalidArgumentException(json_last_error_msg());
                }
                return $result;
            },
            'decode' => function($data, $options = []) {
                $defaults = [
                    'array' => true,
                    'depth' => 512,
                    'flag'  => 0
                ];
                $options += $defaults;
                $result = json_decode($data, $options['array'], $options['depth'], $options['flag']);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new InvalidArgumentException(json_last_error_msg());
                }
                return $result;
            }
        ]);

        static::set('form', ['application/x-www-form-urlencoded'], [
            'encode' => 'http_build_query',
            'decode' => function($data) {
                $decoded = array();
                parse_str($data, $decoded);
                return $decoded;
            }
        ]);
    }
}

Media::reset();
