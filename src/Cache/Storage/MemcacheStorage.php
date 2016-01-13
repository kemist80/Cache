<?php

namespace Kemist\Cache\Storage;

/**
 * MemcacheStorage
 * 
 * @package Kemist\Cache
 *
 * @version 1.1.2
 */
class MemcacheStorage extends AbstractStorage implements StorageInterface {

  /**
   * Server ip
   * @var string 
   */
  protected $server;

  /**
   * Port number
   * @var int 
   */
  protected $port;

  /**
   * Info method
   * @var string 
   */
  protected $info_method = 'getStats';

  /**
   * Constructor
   * 
   * @param array $options
   */
  public function __construct($memcache, array $options = array()) {
    $this->prefix = (isset($options['prefix']) ? $options['prefix'] : '');
    $this->server = (isset($options['server']) ? $options['server'] : '127.0.0.1');
    $this->port = (isset($options['port']) ? $options['port'] : 11211);
    $this->provider = $memcache;
  }

  /**
   * Initialise Cache storage
   * 
   * @return bool
   * 
   * @throws \Kemist\Cache\Exception
   */
  public function init() {
    return $this->provider->connect($this->server, $this->port);
  }

  /**
   * Checks if the specified name in cache exists
   * 	 
   * @param string $name cache name
   *
   * @return bool
   */
  public function exist($name) {
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
   * @param bool $compressed
   *
   * @return bool
   */
  public function put($name, $val, $compressed = false) {
    $real_name = $this->prefix . $name;
    $ret = true;
    if ($compressed && $this->provider->replace($real_name, $val, 2) == false) {
      $ret = $this->provider->set($real_name, $val, 2);
    } elseif ($this->provider->replace($real_name, $val) == false) {
      $ret = $this->provider->set($real_name, $val);
    }
    $ret ? $this->storeName($name) : null;
    return $ret;
  }

  /**
   * Destructor
   * 
   * @return type
   */
  public function __destruct() {
    return is_object($this->provider) ? $this->provider->close() : null;
  }

}
