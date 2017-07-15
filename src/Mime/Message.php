<?php
namespace Lead\Net\Mime;

use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;
use Lead\Set\Set;
use Lead\Net\Mime\Stream\MimeStream;
use Lead\Net\Mime\Stream\PartStream;
use Lead\Net\Behavior\HasBodyTrait;
use Lead\Net\Behavior\HasContentTypeTrait;

/**
 * MIME-compliant email messages class.
 */
class Message
{
    use HasBodyTrait, HasContentTypeTrait;

    /**
     * MIME protocol version number
     *
     * @var string
     */
    protected $_version = '1.0';

    /**
     * The headers instance.
     *
     * @var object
     */
    public $headers = null;

    /**
     * The hostname.
     *
     * @var string
     */
    protected $_host = 'localhost.localdomain';

    /**
     * The word wrap.
     *
     * @var boolean
     */
    protected $_wordWrap = true;

    /**
     * Multipart inlines attachment
     *
     * @var object
     */
    protected $_inlines = null;

    /**
     * Multipart attachments
     *
     * @var object
     */
    protected $_attachments = null;

    /**
     * Alt body message
     *
     * @var string
     */
    protected $_altBody = null;

    /**
     * From email
     *
     * @var string
     */
    protected $_from = null;

    /**
     * Return Path email
     *
     * @var string
     */
    protected $_returnPath = null;

    /**
     * Return Path email
     *
     * @var array
     */
    protected $_recipients = null;

    /**
     * Adds config values to the public properties when a new object is created.
     *
     * @param array $config Configuration options. Possible values are:
     *                      - `'version'`  _string_ : MIME version (defaults `'1.0'`).
     *                      - `'headers'`  _mixed_  : headars (defaults `[]`).
     *                      - `'classes'`  _array_  : class dependencies.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'version'   => '1.0',
            'host'      => 'localhost.localdomain',
            'mime'      => null,
            'charset'   => null,
            'headers'   => [],
            'body'      => '',
            'altBody'   => '',
            'classes'   => [
                'stream'    => 'Lead\Net\Mime\Stream\MimeStream',
                'address'   => 'Lead\Net\Mime\Address',
                'headers'   => 'Lead\Net\Mime\Headers',
                'addresses' => 'Lead\Net\Mime\Header\Addresses'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $class = $this->_classes['headers'];

        $headers = $config['headers'];
        $this->headers = is_object($headers) ? $headers : new $class(['data' => $headers]);
        $this->headers['Date'] = date('r');

        $this->_initContentType($config['mime'], $config['charset']);

        $this->version($config['version']);
        $this->host($config['host']);

        $addresses = $this->_classes['addresses'];
        $this->_recipients = new $addresses();

        $this->body($config['body']);
        $this->altBody($config['body']);
    }

    /**
     * Gets/sets the MIME protocol version number.
     *
     * @param  string      The version number.
     * @return string|self
     */
    public function version($version = null)
    {
        if (func_num_args() === 0) {
            return $this->_version;
        }
        $this->headers->prepend('MIME-Version', $version);
        $this->_version = $version;
        return $this;
    }

    /**
     * Get/set the host.
     *
     * @param  string      $host The host of the message
     * @return string|self
     */
    public function host($host = null)
    {
        if (!func_num_args()) {
            return $this->_host;
        }
        $this->_host = $host;
        return $this;
    }

    /**
     * Get/set email priority.
     *
     * @param  integer $priority.
     * @return mixed
     */
    public function priority($priority = null)
    {
        if (!func_num_args()) {
            return $this->headers['X-Priority'];
        }
        $this->headers['X-Priority'] = (string) $priority;
        return $this;
    }

    /**
     * Get/set the word wrap mode.
     *
     * @param  boolean      $wordWrap Enabling or not the word wrapping
     * @return boolean|self
     */
    public function wordWrap($wordWrap = null)
    {
        if (!func_num_args()) {
            return $this->_wordWrap;
        }
        $this->_wordWrap = !!$wordWrap;
        return $this;
    }

    /**
     * Get/Set the sender email.
     *
     * @param  string $address The sender email address.
     * @param  mixed  $name    The sender name.
     * @return self
     */
    public function from($address = null, $name = null)
    {
        if (!func_num_args()) {
            return $this->_from;
        }
        $class = $this->_classes['address'];
        $this->_from = new $class($address, $name);
        $this->headers['From'] = $this->_from->toString();
        return $this;
    }

    /**
     * Get/Set the subject of the message.
     *
     * @param  string $subject The subject.
     * @return self
     */
    public function subject($subject = null)
    {
        if (!func_num_args()) {
            return $this->headers['Subject'];
        }
        $this->headers['Subject'] = Mime::encodeValue($subject, MIME::MAX_LINE_LENGTH, Mime::FOLDING);
        return $this;
    }

    /**
     * Get/set the Return-Path header of the message.
     *
     * @param  string $returnPath The sender email address.
     * @return mixed
     */
    public function returnPath($returnPath)
    {
        if (!func_num_args()) {
            return $this->_returnPath;
        }
        $this->_returnPath = $returnPath;
        $this->headers['Return-Path'] = Mime::encodeEmail($returnPath);
        return $this;
    }

    /**
     * Add a reply-to address.
     *
     * @param  string $address The email address.
     * @param  mixed  $name    The name.
     * @return self
     */
    public function addReplyTo($address, $name = null)
    {
        $this->_addRecipient('Reply-To', $address, $name);
        return $this;
    }

    /**
     * Add an email recipient.
     *
     * @param  string $address The email address.
     * @param  mixed  $name    The name.
     * @return self
     */
    public function addTo($address, $name = null)
    {
        $address = $this->_addRecipient('To', $address, $name);
        $this->_recipients[$address->email()] = $address;
        return $this;
    }

    /**
     * Adds carbon copy email recipient.
     *
     * @param  string $address The email address.
     * @param  mixed  $name    The name.
     * @return self
     */
    public function addCc($address, $name = null)
    {
        $address = $this->_addRecipient('Cc', $address, $name);
        $this->_recipients[$address->email()] = $address;
        return $this;
    }

    /**
     * Adds blind carbon copy email recipient.
     *
     * @param  string $address The sender email address.
     * @param  mixed  $name    The sender name.
     * @return self
     */
    public function addBcc($address, $name = null)
    {
        $class = $this->_classes['address'];
        $bcc = new $class($address, $name);
        $this->_recipients[$bcc->email()] = $bcc;
        return $this;
    }

    /**
     * Add a recipient to a specific type section.
     *
     * @param  string $type    The type of recipient.
     * @param  string $address The email address.
     * @param  mixed  $name    The name.
     * @return objedt          The created address.
     */
    protected function _addRecipient($type, $address, $name = null)
    {
        $classes = $this->_classes;
        if (!isset($this->headers[$type])) {
            $addresses = $classes['addresses'];
            $this->headers[$type] = new $addresses();
        }
        $class = $classes['address'];
        $value = new $class($address, $name);
        $this->headers[$type][] = $value;
        return $value;
    }

    /**
     * Return recipients
     *
     * @return array
     */
    public function recipients()
    {
        return $this->_recipients;
    }

    /**
     * Return inlines
     *
     * @return object
     */
    public function inlines()
    {
        return $this->_inlines;
    }

    /**
     * Return attachments
     *
     * @return object
     */
    public function attachments()
    {
        return $this->_attachments;
    }

    /**
     * Add an embedded file.
     */
    public function addInline($path, $name = null, $mime = true, $encoding = true)
    {
        $filename = static::sanitize(basename($path));
        $name = $name ?: $filename;
        $this->_inlines[$path] = [
            'name'        => $name,
            'filename'    => $filename,
            'disposition' => 'attachment',
            'mime'        => $mime,
            'headers'     => [
                'Content-ID' => static::generateId($this->host())
            ]
        ];
    }

    /**
     * Add an attachment.
     */
    public function addAttachment($path, $name = null, $mime = true, $encoding = true)
    {
        $filename = static::sanitize(basename($path));
        $name = $name ?: $filename;
        $this->_attachments[$path] = [
            'name'        => $name,
            'filename'    => $filename,
            'disposition' => 'attachment',
            'mime'        => $mime,
            'headers'     => [
                'Content-ID' => static::generateId($this->host())
            ]
        ];
    }

    /**
     * Get/set the alt body message.
     *
     * @param  string      $value.
     * @return string|self
     */
    public function altBody($value = null)
    {
        if (!func_num_args()) {
            return $this->_altBody;
        }
        $this->_altBody = (string) $value;
        return $this;
    }

    /**
     * Set the HTML body message with an optionnal alternative body.
     *
     * @param  string      $body    The HTML body message.
     * @param  string      $altBody The alt body.
     * @return string|self
     */
    public function html($body, $altBody = null)
    {
        if (!func_num_args()) {
            return $this->_body;
        }
        $this->mime('text/html');
        $this->charset('UTF-8');
        $this->body((string) $body);
        if ($altBody === 'true') {
            $this->_altBody = static::stripTags($this->_body);
        } elseif ($altBody !== null) {
            $this->_altBody = static::stripTags($altBody);
        }
        return $this;
    }

    /**
     * Magic method to convert the instance into an HTTP message string.
     *
     * @return string
     */
    public function toMessage()
    {
        $message = clone $this;

        $stream = $this->_classes['stream'];

        $body = $message->body();
        $altBody = $message->altBody();
        $attachments = $message->attachments();
        $inlines = $message->inlines();

        $bodyMime = $message->mime() ?: 'text/plain';
        $bodyCharset = $message->charset() ?: 'UTF-8';

        $message->headers['Message-ID'] = static::generateId($message->host());
        $main = $cursor = $message->stream();

        if (count($attachments)) {
            $cursor->mime('multipart/mixed');
            $current = $cursor->add(new $stream(['mime' => 'multipart/alternative']));
            foreach ($attachments as $path => $attachment) {
                $current->add(fopen($path, 'r'), $attachment);
            }
            $cursor = $current;
        } elseif ($altBody || count($inlines)) {
            $cursor->mime('multipart/alternative');
            $current = $cursor->add(new $stream(['mime' => 'multipart/alternative']));
            $cursor = $current;
        }

        if ($altBody) {
            $cursor->add($altBody, [
                'mime'     => 'text/plain',
                'charset'  => 'ASCII',
                'encoding' => Mime::optimalEncoding($body)
            ]);
        }

        if ($body) {
            $options = [
                'data'     => $body,
                'mime'     => $bodyMime,
                'charset'  => $bodyCharset,
                'encoding' => Mime::optimalEncoding($body)
            ];
            if ($inlines) {
                $cursor->mime('multipart/related');
                $current = $cursor->add(new $stream(['mime' => 'multipart/related']));
                $current->add($body, $options);
                foreach ($inlines as $path => $inline) {
                    $current->add(fopen($path, 'r'), $inline);
                }
            } else {
                $cursor->add($body, $options);
            }
        }

        $message->mime($main->mime());

        return (string) $message->headers . $main->toString();
    }

    public function _buildMixed($message)
    {
        $attachments = $message->attachments();
        if (!$attachments) {
            return;
        }

        foreach ($attachments as $path => $attachment) {
            $mimeMixed->add(fopen($path, 'r'), $attachment);
        }
        return $mimeMixed;
    }

    public function _buildRelated($message)
    {
        $inlines = $message->inlines();
        if (!$inlines) {
            return;
        }
        $mimeRelated = new MimeStream(['mime' => 'multipart/related']);

        return $mimeRelated;
    }

    /**
     * Clone the message.
     */
    public function __clone()
    {
        $this->headers = clone $this->headers;
    }

    /**
     * Unique ID Generator.
     *
     * @param  string hostname
     * @return string
     */
    public static function generateId($host)
    {
        return sprintf('<%s@%s>', md5(uniqid(time())), $host);
    }

    /**
     * Strip HTML tags
     *
     * @param  string $html The html content
     * @return string
     */
    public static function stripTags($html, $charset = 'UTF-8')
    {
        $patterns = [
            '~<(style|script|head).*</\\1>~Uis' => '',
            '~<t[dh][ >]~i' => ' $0',
            '~<a\s[^>]*href=(?|"([^"]+)"|\'([^\']+)\')[^>]*>(.*?)</a>~is' => '$2 &lt;$1&gt;',
            '~[\r\n]+~' => ' ',
            '~<(/?p|/?h\d|li|br|/tr)[ >/]~i' => "\n$0",
        ];

        $text = preg_replace(array_keys($patterns), array_values($patterns), $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, $charset);
        $text = preg_replace('~[ \t]+~', ' ', $text);

        return trim($text);
    }
}
