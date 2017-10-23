<?php
namespace Lead\Net\Http;

use Lead\Set\Set;
use Lead\Net\Headers;

/**
 * Support Multipart message with HTTP headers as well as single part message.
 */
class MixedPart extends \Lead\Net\MixedPart
{
    /**
     * The constructor
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $config += [
            'headers' => null
        ];
        $config['headers'] = is_object($config['headers']) ?  $config['headers'] : new Headers([
            'data'      => $config['headers'],
            'length'    => 0,
            'maxLength' => 2998
        ]);
        parent::__construct($config);
    }

}
