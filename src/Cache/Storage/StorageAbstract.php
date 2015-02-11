<?php

namespace Kemist\Cache\Storage;

/**
 * StorageAbstract class
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.4
 */
abstract class StorageAbstract {

  /**
   * Cached field names
   * 	 	
   * @var array
   */
  protected $_fields = array();

  /**
   * Number of hits
   * @var int
   */
  protected $_hits = 0;

  /**
   * Number of misses
   * @var int
   */
  protected $_misses = 0;

  /**
   * Cache provider Object
   * @var object
   */
  protected $_provider;

  /**
   * Info method
   * @var string 
   */
  protected $_info_method = 'info';

  /**
   * Key prefix to avoid collisions
   * @var string 
   */
  protected $_prefix = '';

  /**
   * Retrieves the content of $name cache
   * 	 
   * @param string $name cache name
   * @param bool $compressed
   *
   * @return mixed
   */
  public function get($name, $compressed = false) {
    $ret = $this->_provider->get($this->_prefix . $name);
    if ($ret !== false) {
      $this->hit();
      $this->_storeName($name);
    } else {
      $this->miss();
    }

    return $ret;
  }

  /**
   * Cache miss occured
   */
  public function miss() {
    $this->_misses++;
  }

  /**
   * Cache hit occured
   */
  public function hit() {
    $this->_hits++;
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
      return $this->_provider->flush();
    } else {
      return $this->_provider->delete($this->_prefix . $name);
    }
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
    $classname = explode('\\', get_class($this));
    $ret['CACHE_TYPE'] = end($classname);
    $ret['CACHE_HITS'] = $this->_hits;
    $ret['CACHE_MISSES'] = $this->_misses;

    $ret = array_merge($ret, call_user_func(array($this->_provider, $this->_info_method)));

    if ($get_fields) {
      foreach ($this->_fields as $field) {
        $ret['field_content'][$field] = $this->get($field);
      }
    }

    return $ret;
  }

  /**
   * Retrieves cache hits
   * 
   * @return int
   */
  public function getHits() {
    return $this->_hits;
  }

  /**
   * Retrieves cache misses
   * 
   * @return int
   */
  public function getMisses() {
    return $this->_misses;
  }

  /**
   * Stores cache name
   * 
   * @param string $name
   */
  protected function _storeName($name) {
    if (!in_array($name, $this->_fields)) {
      $this->_fields[] = $name;
    }
  }

}
