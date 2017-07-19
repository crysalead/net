<?php
namespace Lead\Net\Transport\Protocol;

use InvalidArgumentException;
use Lead\Net\NetException;

/**
 * RFC821 SMTP email transport class.
 */
class Smtp
{
    /**
     * Connection.
     *
     * @var resource
     */
    protected $_connection = null;

    /**
     * Client FQDN/IP.
     *
     * @var string
     */
    protected $_client = null;

    /**
     * SMTP Host FQDN/IP.
     *
     * @var string
     */
    protected $_host = null;

    /**
     * Port number.
     *
     * @var integer
     */
    protected $_port = 25;

    /**
     * Connection username.
     *
     * @var string
     */
    protected $_username = '';

    /**
     * Connection password.
     *
     * @var string
     */
    protected $_password = '';

    /**
     * Secure mode (ssl|tls).
     *
     * @var string
     */
    protected $_secure = '';

    /**
     * Timeout value.
     *
     * @var integer
     */
    protected $_timeout = 10;

    /**
     * The context.
     *
     * @var resource
     */
    protected $_context = null;

    /**
     * Connection persistence.
     *
     * @var boolean
     */
    protected $_persistent = false;

    public function __construct($options = [])
    {
        $defaults = [
            'client'     => null,
            'host'       => null,
            'port'       => !isset($options['secure']) || $options['secure'] === 'ssl' ? 465 : 25,
            'username'   => null,
            'password'   => null,
            'secure'     => 465,
            'timeout'    => 10,
            'context'    => [],
            'persistent' => false
        ];

        $options += $defaults;

        if (empty($options['client'])) {
            throw new InvalidArgumentException('Client FQDN/IP is missing.');
        }
        if (empty($options['host'])) {
            throw new InvalidArgumentException('SMTP FQDN/IP is missing.');
        }

        $this->_secure = $options['secure'];
        $this->_client = $options['client'];
        $this->_host = $options['host'];
        $this->_port = (int) $options['port'];

        $this->_username = $options['username'];
        $this->_password = $options['password'];

        $this->_timeout = (int) $options['timeout'];

        $this->_context = $options['context'] ? stream_context_create($options['context']) : stream_context_get_default();
        $this->_persistent = !!$options['persistent'];
    }

    /**
     * Connects and authenticates to SMTP server.
     */
    protected function connect()
    {
        $scheme = ($this->_secure === 'ssl' ? 'ssl://' : '');
        $url = $scheme . $this->_host . ':' . $this->_port;
        $this->_connection = stream_socket_client($url, $errno, $error, $this->_timeout, STREAM_CLIENT_CONNECT, $this->_context);
        if (!$this->_connection) {
            throw new NetException($error, $errno);
        }
        stream_set_timeout($this->_connection, $this->_timeout, 0);
        $this->read();

        $this->write("EHLO {$this->_client}");
        $ehloResponse = $this->read();

        if ($this->_secure === 'tls') {
            $this->write('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->_connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new NetException('Unable to connect via TLS.');
            }
            $this->write("EHLO {$this->_client}", 250);
        }

        if ($this->_username !== null && $this->_password !== null) {
            $authMechanisms = [];
            if (preg_match('~^250[ -]AUTH (.*)$~im', $ehloResponse, $matches)) {
                $authMechanisms = explode(' ', trim($matches[1]));
            }

            if (in_array('PLAIN', $authMechanisms, true)) {
                $credentials = $this->_username . "\0" . $this->_username . "\0" . $this->_password;
                $this->write('AUTH PLAIN ' . base64_encode($credentials), 235, 'PLAIN credentials');
            } else {
                $this->write('AUTH LOGIN', 334);
                $this->write(base64_encode($this->_username), 334, 'username');
                $this->write(base64_encode($this->_password), 235, 'password');
            }
        }
    }

    /**
     * Reads response from server.
     */
    protected function read()
    {
        $buffer = '';
        while (($line = fgets($this->_connection, 1000)) != null) { // intentionally ==
            $buffer .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $buffer;
    }

    /**
     * Writes data to server and checks response against expected code if some provided.
     * @param  int|int[] $expectedCode
     */
    protected function write($line, $expectedCode = null, $message = null)
    {
        fwrite($this->_connection, $line . "\r\n");
        if (!$expectedCode) {
            return;
        }
        $response = $this->read();
        if (!in_array((int) $response, (array) $expectedCode, true)) {
            throw new NetException('SMTP server did not accept ' . ($message ? $message : $line) . ' with error: ' . trim($response));
        }
    }

    /**
     * Sends email.
     *
     * @param  object $mail A mail message.
     *
     * @throws NetException
     */
    public function send($mail)
    {
        $mail = clone $mail;

        try {
            if (!$this->_connection) {
                $this->connect();
            }

            if (($from = $mail->returnPath()) || ($from = $mail->from())) {
                $this->write("MAIL FROM:<" . $from->email() . ">", 250);
            }

            foreach ($mail->recipients() as $recipient) {
                $this->write("RCPT TO:<" . $recipient->email() . ">", [250, 251]);
            }

            $this->write('DATA', 354);
            $this->write(preg_replace('~^\.~m', '..', $mail->toMessage()));
            $this->write('.', 250);

            if (!$this->_persistent) {
                $this->write('QUIT', 221);
                $this->disconnect();
            }
        } catch (NetException $exception) {
            if ($this->_connection) {
                $this->disconnect();
            }
            throw $exception;
        }
    }

    /**
     * Disconnects from SMTP server.
     */
    protected function disconnect()
    {
        fclose($this->_connection);
        $this->_connection = null;
    }
}
