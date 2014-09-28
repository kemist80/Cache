<?php

namespace Kemist\Cache\Storage;

/**
 * Memcache Storage
 * 
 * @package Kemist\Cache
 *
 * @version 1.0.10
 */
class Memcache extends Service implements StorageInterface {

  /**
   * Server ip
   * @var string 
   */
  protected $_server;

  /**
   * Port number
   * @var int 
   */
  protected $_port;

  /**
   * Info method
   * @var string 
   */
  protected $_info_method = 'getStats';

  /**
   * Constructor
   * 
   * @param array $options
   */
  public function __construct($memcache, array $options = array()) {
    $this->_prefix = (isset($options['prefix']) ? $options['prefix'] : '');
    $this->_server = (isset($options['server']) ? $options['server'] : '127.0.0.1');
    $this->_port = (isset($options['port']) ? $options['port'] : 11211);
    $this->_service = $memcache;
  }

  /**
   * Initialise Cache storage
   * 
   * @return bool
   * 
   * @throws \Kemist\Cache\Exception
   */
  public function init() {
    return $this->_service->connect($this->_server, $this->_port);
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
   * Saves the variable to the $name cache
   * 	 
   * @param string $name cache name
   * @param mixed $val variable to be stored
   * @param bool $compressed
   *
   * @return bool
   */
  public function put($name, $val, $compressed = false) {
    $real_name = $this->_prefix . $name;
    $ret = true;
    if ($compressed && $this->_service->replace($real_name, $val, 2) == false) {
      $ret = $this->_service->set($real_name, $val, 2);
    } elseif ($this->_service->replace($real_name, $val) == false) {
      $ret = $this->_service->set($real_name, $val);
    }
    $ret ? $this->_storeName($name) : null;
    return $ret;
  }

  /**
   * Destructor
   * 
   * @return type
   */
  public function __destruct() {
    return is_object($this->_service) ? $this->_service->close() : null;
  }

}
