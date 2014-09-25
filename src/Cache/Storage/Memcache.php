<?php

namespace Kemist\Cache\Storage;

/**
 * Memcache Storage
 * 
 * @package Kemist\Cache
 *
 * @version 1.0.4
 */
class Memcache implements StorageInterface {

  /**
   * Memcache object
   * @var object
   */
  protected $_memcache;

  /**
   * Number of hits
   * @var int
   */
  protected $_hits = 0;

  /**
   * Cached field names	
   * @var array
   */
  protected $_fields = array();

  /**
   * Key prefix to avoid collisions
   * @var string 
   */
  protected $_prefix;

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
   * Constructor
   * 
   * @param array $options
   */
  public function __construct($memcache, array $options = array()) {
    $this->_prefix = (isset($options['prefix']) ? $options['prefix'] : '');
    $this->_server = (isset($options['server']) ? $options['server'] : $_SERVER['SERVER_ADDR']);
    $this->_port = (isset($options['port']) ? $options['port'] : 11211);
    $this->_memcache = $memcache;
  }

  /**
   * Initialise Cache storage
   * 
   * @return bool
   * 
   * @throws \Kemist\Cache\Exception
   */
  public function init() {
    return $this->_memcache->connect($this->_server, $this->_port);
  }

  /**
   * Checks if the specified name in cache exists
   * 	 
   * @param string $name cache name
   *
   * @return bool
   */
  public function exist($name) {
    if ($this->_memcache->get($this->_prefix . $name)) {
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
      return $this->_memcache->flush();
    } else {
      return $this->_memcache->delete($this->_prefix . $name);
    }
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
    if ($compressed && $this->_memcache->replace($real_name, $val, 2) == false) {
      $ret = $this->_memcache->set($real_name, $val, 2);
    } elseif ($this->_memcache->replace($real_name, $val) == false) {
      $ret = $this->_memcache->set($real_name, $val);
    }
    if ($ret && !in_array($name, $this->_fields)) {
      $this->_fields[] = $name;
    }
    return $ret;
  }

  /**
   * Retrieves the content of $name cache
   * 	 
   * @param string $name cache name
   * @param bool $compressed
   *
   * @return mixed
   */
  public function get($name, $compressed = false) {
    $ret = $this->_memcache->get($this->_prefix . $name);
    if ($ret !== false) {
      $this->_hits++;

      if (!in_array($name, $this->_fields)) {
        $this->_fields[] = $name;
      }
    }

    return $ret;
  }

  /**
   * Retrieves information of Cache state
   * 
   * @param bool $get_fields
   *  
   * @return array
   */
  public function info($get_fields = false) {
    $ret = array();
    $ret['CACHE_TYPE'] = 'Memcache';
    $ret['CACHE_HITS'] = $this->_hits;

    $ret = array_merge($ret, $this->_memcache->getStats());

    if ($get_fields) {
      foreach ($this->_fields as $field) {
        $ret['field_content'][$field] = $this->get($field);
      }
    }

    return $ret;
  }

  /**
   * Destructor
   * 
   * @return type
   */
  public function __destruct() {
    return is_object($this->_memcache) ? $this->_memcache->close() : null;
  }

}
