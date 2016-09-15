<?php

namespace Kemist\Cache\Storage;

/**
 * ApcStorage
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.10
 */
class ApcStorage extends AbstractStorage implements StorageInterface {

  /**
   * Constructor
   * 
   * @param array $options
   */
  public function __construct(ApcObject $apc, array $options = array()) {
    $this->prefix = (isset($options['prefix']) ? $options['prefix'] : '');
    $this->provider = $apc;
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
  public function has($name) {
    if ($this->provider->get($this->prefix . $name)) {
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
  public function store($name, $val, $compressed = false) {
    $success = $this->provider->store($this->prefix . $name, $val);
    $success ? $this->storeName($name) : null;
    return $success;
  }

}
