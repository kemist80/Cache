<?php

namespace Kemist\Cache\Storage;

/**
 * Apc Storage
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.5
 */
class Apc extends Service implements StorageInterface {

  /**
   * Constructor
   * 
   * @param array $options
   */
  public function __construct(ApcObject $apc, array $options = array()) {
    $this->_prefix = (isset($options['prefix']) ? $options['prefix'] : '');
    $this->_service = $apc;
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
    if ($this->_service->get($this->_prefix . $name)) {
      return true;
    }
    return false;
  }

  /**
   * Deletes the specified cache or each one if '' given
   * 	 
   * @param string $name cache name
   *
   * @return bool
   */
  public function clear($name = '') {
    if ($name == '') {
      return $this->_service->flush();
    } else {
      return $this->_service->clear($this->_prefix . $name);
    }
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
    $ret = $this->_service->put($this->_prefix . $name, $val);
    if ($ret && !in_array($name, $this->_fields)) {
      $this->_fields[] = $name;
    }
    return $ret;
  }

}
