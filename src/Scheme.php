<?php
namespace net;

class Scheme
{
    const DEFAULTS = [
        'ftp'    => 21,
        'ssh'    => 22,
        'telnet' => 23,
        'smtp'   => 25,
        'http'   => 80,
        'sftp'   => 115,
        'imap'   => 143,
        'https'  => 443,
        'smtps'  => 587
    ];

    protected static $_schemes = Scheme::DEFAULTS;

    /**
     * Registers a scheme and its associated default port.
     *
     * @param  string  $name The scheme name.
     * @param  integer $port The default port number.
     */
    public static function register($name, $port)
    {
        static::$_schemes[$name] = (integer) $port;
    }

    /**
     * Unregisters a scheme.
     *
     * @param  string  $name The scheme name.
     */
    public static function unregister($name)
    {
        unset(static::$_schemes[$name]);
    }

    /**
     * Checks if a scheme exists or returns all registered schemes with no parameters.
     *
     * @param  string        $name The scheme name.
     * @return boolean|array       Returns `true` if the scheme exists, `false` otherwise.
     *                             If `$name` is null returns all registered schemes.
     */
    public static function registered($name = null)
    {
        if ($name) {
            return isset(static::$_schemes[$name]);
        }
        return static::$_schemes;
    }

    /**
     * Returns a scheme default port.
     *
     * @param  string  $name The scheme name.
     * @return integer       The associated default port number.
     */
    public static function port($name)
    {
        if (!isset(static::$_schemes[$name])) {
            throw new NetException("Unregistered scheme `{$name}`.");
        }
        return static::$_schemes[$name];
    }

    /**
     * Resets schemes to defaults.
     */
    public static function reset()
    {
        static::$_schemes = static::DEFAULTS;
    }
}
