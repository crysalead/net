<?php
namespace Lead\Net\Mime;

use InvalidArgumentException;
use Lead\Set\Set;

/**
 * MIME-compliant email messages class.
 */
class Message
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * MIME protocol version number
     *
     * @var string
     */
    protected $_version = '1.0';

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
     *                      - `'classes'`  _array_  : class dependencies.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'version'   => '1.0',
            'host'      => 'localhost.localdomain',
            'mime'      => null,
            'charset'   => null,
            'body'      => '',
            'altBody'   => '',
            'headers'   => [],
            'classes'   => [
                'address'   => 'Lead\Net\Mime\Address',
                'addresses' => 'Lead\Net\Mime\Header\Addresses'
            ]
        ];
        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];

        $addresses = $this->_classes['addresses'];
        $this->_recipients = new $addresses();

        $this->body($config['body']);
        $this->altBody($config['body']);

        $this->_stream = new MixedPart(['headers' => $config['headers']]);

        $this->version($config['version']);
        $this->host($config['host']);
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
        $headers = $this->headers();
        $headers->prepend('MIME-Version', $version);
        $this->_version = $version;
        return $this;
    }

    /**
     * Get/set the headers instance
     *
     * @param  string $headers The headers instance
     * @return string
     */
    public function headers($headers = null)
    {
        if (!func_num_args()) {
            return $this->_stream->headers();
        }
        $this->_stream->headers($headers);
        return $this;
    }

    /**
     * Get/set the mime.
     *
     * @param  string $mime
     * @return string           The mime.
     */
    public function mime($mime = null)
    {
        if (!func_num_args()) {
            return $this->_stream->mime();
        }
        $this->_stream->mime($mime);
        return $this;
    }

    /**
     * Get/set the charset.
     *
     * @param  string $charset
     * @return string           The charset.
     */
    public function charset($charset = null)
    {
        if (!func_num_args()) {
            return $this->_stream->charset();
        }
        $this->_stream->charset($charset);
        return $this;
    }

    /**
     * Get/set the encoding.
     *
     * @param  string $encoding
     * @return string           The encoding.
     */
    public function encoding($encoding = null)
    {
        if (!func_num_args()) {
            return $this->_stream->encoding();
        }
        $this->_stream->encoding($encoding);
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
        $headers = $this->headers();
        if (!func_num_args()) {
            return $headers['X-Priority'];
        }
        $headers['X-Priority'] = (string) $priority;
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

        $headers = $this->headers();
        $headers['From'] = $this->_from->toString();
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
        $headers = $this->headers();
        if (!func_num_args()) {
            return $headers['Subject'];
        }
        $headers['Subject'] = Mime::encodeValue($subject, 998, "\r\n ");
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
        $headers = $this->headers();
        $headers['Return-Path'] = '<' . Mime::encodeEmail($returnPath) .'>';
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
        $headers = $this->headers();
        if (!isset($headers[$type])) {
            $addresses = $classes['addresses'];
            $headers[$type] = new $addresses();
        }
        $class = $classes['address'];
        $value = new $class($address, $name);
        $headers[$type][] = $value;
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
    public function body($value = null)
    {
        if (!func_num_args()) {
            return $this->_body;
        }
        $this->_body = (string) $value;
        return $this;
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

        $this->body($body);
        $this->mime('text/html');
        $this->charset(Mime::optimalCharset($body));

        if ($altBody === 'true') {
            $this->_altBody = static::stripTags($this->_body);
        } elseif ($altBody !== null) {
            $this->_altBody = static::stripTags($altBody);
        }
        return $this;
    }

    /**
     * Get message stream.
     *
     * @return object
     */
    public function stream()
    {
        return $this->_stream;
    }

    /**
     * Magic method to convert the instance into an HTTP message string.
     *
     * @return string
     */
    public function toMessage()
    {
        $message = clone $this;

        $altBody = $message->altBody();
        $attachments = $message->attachments();
        $inlines = $message->inlines();

        $root = $cursor = $message->stream();

        $body = $message->body();
        $bodyMime = $cursor->mime() ?: 'text/plain';
        $bodyCharset = $cursor->charset() ?: $cursor->charset(Mime::optimalCharset($body));

        if (count($attachments)) {
            $cursor->mime('multipart/mixed');
            $current = $cursor->add(new MixedPart(['mime' => 'multipart/alternative']));
            foreach ($attachments as $path => $attachment) {
                $current->add(fopen($path, 'r'), $attachment);
            }
            $cursor = $current;
        } elseif ($altBody || count($inlines)) {
            $cursor->mime('multipart/alternative');
        }

        if ($altBody) {
            $cursor->add($altBody, [
                'mime'     => 'text/plain',
                'charset'  => Mime::optimalCharset($altBody),
                'encoding' => Mime::optimalEncoding($altBody)
            ]);
        }

        if ($body) {
            $options = [
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

        $headers = $root->headers();
        $headers['Date'] = date('r');
        $headers['Message-ID'] = static::generateId($message->host());
        return $root->toMessage();
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
        $this->_stream = clone $this->_stream;
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
