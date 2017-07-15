<?php
namespace Lead\Net\Behavior;

trait HasContentTypeTrait
{
    /**
     * The mime info.
     *
     * @var string
     */
    protected $_mime = null;

    /**
     * The charset info.
     *
     * @var string
     */
    protected $_charset = null;

    /**
     * Gets/sets the Content-Type.
     *
     * @param  string      $mime A full Content-Type i.e. `'application/json'`.
     * @return string|self
     */
    public function mime($mime = null)
    {
        if (!func_num_args()) {
            list($mime, $charset) = $this->_inspectContentType();
            return $this->_mime = $mime;
        }
        unset($this->headers['Content-Type']);
        if ($this->_mime = $mime) {
            $charset = $this->charset();
            $this->headers['Content-Type'] = $mime . ($charset ? '; charset=' . $charset : '');
        } else {
            $this->_mime = null;
        }
        return $this;
    }

    /**
     * Gets/sets the Content-Type charset.
     *
     * @param  string      $charset A charset i.e. `'UTF-8'`.
     * @return string|self
     */
    public function charset($charset = null)
    {
        if (!func_num_args()) {
            return $this->_charset;
        }
        $mime = $this->mime();
        unset($this->headers['Content-Type']);
        if ($charset) {
            $this->_charset = strtoupper($charset);
            if ($mime) {
                $this->headers['Content-Type'] = $mime . ($this->_charset ? '; charset=' . $this->_charset : '');
            }
        } elseif ($mime) {
            $this->_charset = null;
            $this->headers['Content-Type'] = $mime;
        }
        return $this;
    }

    /**
     * Init default mime/charset values.
     *
     * @param  string      $mime    A mime or `null` to use the default one.
     * @param  string      $charset A charset or `null` to use the default one.
     */
    protected function _initContentType($mime, $charset)
    {
        if (!isset($this->headers['Content-Type'])) {
            $this->mime($mime);
            $this->charset($charset);
            return;
        }

        list($mime, $charset) = $this->_inspectContentType();
        unset($this->headers['Content-Type']);

        $this->mime($mime);
        $this->charset($charset);
    }

    /**
     * Extract the Content Type (mime + charset) from headers
     *
     * @return array
     */
    protected function _inspectContentType()
    {
        $mime = null;
        $charset = null;
        if (preg_match('/([-\w\/\.+]+)(;\s*?charset=(.+))?/i', $this->headers['Content-Type']->value(), $matches)) {
            $mime = $mime ?: $matches[1];
            if (isset($matches[3])) {
                $charset = $charset ?: $matches[3];
            }
        }
        return [$mime, $charset];
    }

}