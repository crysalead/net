<?php
namespace Lead\Net\Transport;

class Callback
{
    protected $_context = null;

    protected $_sequential = false;

    protected $_closures = [];

    public function __construct($config = [])
    {
        if (isset($config['context'])) {
            $this->_context = $config['context'];
        }
        if (isset($config['closure'])) {
            $this->_add($config['closure']);
        }
    }

    protected function _add($closure)
    {
        $this->_closures[] = $this->_context ? $closure->bindTo($this->_context) : $closure;
    }

    public function then($closure)
    {
        $this->_sequential = true;
        $this->_add($closure);
        return $this;
    }

    public function trigger($value = null)
    {
        if (!$closure = $this->_sequential ? array_shift($this->_closures) : reset($this->_closures)) {
            return;
        }
        $closure($value);
    }
}