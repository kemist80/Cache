<?php

namespace Kemist\Cache\Storage;

/**
 * Apc Storage
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.8
 */
class Apc extends StorageAbstract implements StorageInterface {

  /**
   * Constructor
   * 
   * @param array $options
   */
  public function __construct(ApcObject $apc, array $options = array()) {
    $this->_prefix = (isset($options['prefix']) ? $options['prefix'] : '');
    $this->_provider = $apc;
  }

  /**
   * Initialise Cache storage
   * 
   * @return boolean
   * 
   * @throws \Kemist\Cache\Exception
   */
  public function init() {
    return true;
  }

  /**
   * Checks if the specified name in cache exists
   * 	 
   * @param string $name cache name
   *
   * @return bool
   */
  public function exist($name) {
    if ($this->_provider->get($this->_prefix . $name)) {
      return true;
    }
    return false;
  }

  /**
   * Saves the variable to the $name cache
   * 	 
   * @param string $name cache name
   * @param mixed $val variable to be stored
   *
   * @return bool
   */
  public function put($name, $val, $compressed = false) {
    $ret = $this->_provider->put($this->_prefix . $name, $val);
    $ret ? $this->_storeName($name) : null;
    return $ret;
  }

}
