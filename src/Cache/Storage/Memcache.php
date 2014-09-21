<?php

namespace Kemist\Cache\Storage;

/**
 * Memcache Storage
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.0
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
   */
  public function __construct(array $options = array()) {
    $this->_prefix = (isset($options['prefix']) ? $options['prefix'] : '');
    $this->_server = (isset($options['server']) ? $options['server'] : $_SERVER['SERVER_ADDR']);
    $this->_port = (isset($options['port']) ? $options['port'] : 11211);
  }

  /**
   * Initialise Cache storage
   * 
   * @return boolean
   * 
   * @throws \Kemist\Cache\Exception
   */
  public function init() {
    if (!class_exists('Memcache') || !extension_loaded('memcache')) {
      throw new \Kemist\Cache\Exception("Memcache extension not loaded!");
    }

    $this->_memcache = new \Memcache();
    return $this->_memcache->connect($this->_server, $this->_port);
  }

  /**
   * Check if $name cache exists and not older than $max_age
   * 	 
   * @param string $name cache name
   * @param int $max_age cache max lifetime
   *
   * @return bool
   */
  public function exist($name, $max_age = 0) {
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
    if ($compressed) {
      if ($this->_memcache->replace($real_name, $val, MEMCACHE_COMPRESSED) == false) {
        $ret = $this->_memcache->set($real_name, $val, MEMCACHE_COMPRESSED);
      }
    } else {
      if ($this->_memcache->replace($real_name, $val) == false) {
        $ret = $this->_memcache->set($real_name, $val);
      }
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

  public function __destruct() {
    return is_object($this->_memcache) ? $this->_memcache->close() : null;
  }

}

?>
