<?php

namespace Kemist\Cache\Storage;

/**
 * AbstractStorage class
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.5
 */
abstract class AbstractStorage {

  /**
   * Cached field names
   * 	 	
   * @var array
   */
  protected $fields = array();

  /**
   * Number of hits
   * @var int
   */
  protected $hits = 0;

  /**
   * Number of misses
   * @var int
   */
  protected $misses = 0;

  /**
   * Cache provider Object
   * @var object
   */
  protected $provider;

  /**
   * Info method
   * @var string 
   */
  protected $info_method = 'info';

  /**
   * Key prefix to avoid collisions
   * @var string 
   */
  protected $prefix = '';

  /**
   * Retrieves the content of $name cache
   * 	 
   * @param string $name cache name
   * @param bool $compressed
   *
   * @return mixed
   */
  public function get($name, $compressed = false) {
    $ret = $this->provider->get($this->prefix . $name);
    if ($ret !== false) {
      $this->hit();
      $this->storeName($name);
    } else {
      $this->miss();
    }

    return $ret;
  }

  /**
   * Cache miss occured
   */
  public function miss() {
    $this->misses++;
  }

  /**
   * Cache hit occured
   */
  public function hit() {
    $this->hits++;
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
      return $this->provider->flush();
    } else {
      return $this->provider->delete($this->prefix . $name);
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
    $ret['CACHE_HITS'] = $this->hits;
    $ret['CACHE_MISSES'] = $this->misses;

    $ret = array_merge($ret, call_user_func(array($this->provider, $this->info_method)));

    if ($get_fields) {
      foreach ($this->fields as $field) {
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
    return $this->hits;
  }

  /**
   * Retrieves cache misses
   * 
   * @return int
   */
  public function getMisses() {
    return $this->misses;
  }

  /**
   * Stores cache name
   * 
   * @param string $name
   */
  protected function storeName($name) {
    if (!in_array($name, $this->fields)) {
      $this->fields[] = $name;
    }
  }

}
