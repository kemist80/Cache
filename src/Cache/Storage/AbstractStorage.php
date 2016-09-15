<?php

namespace Kemist\Cache\Storage;

/**
 * AbstractStorage class
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.6
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
  protected $infoMethod = 'info';

  /**
   * Key prefix to avoid collisions
   * @var string 
   */
  protected $prefix = '';

  /**
   * Retrieves the content of $name cache
   * 	 
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   * @param string $name cache name
   *
   * @return mixed
   */
  public function get($name, $compressed = false) {
    $value = $this->provider->get($this->prefix . $name);
    if ($value !== false) {
      $this->hit();
      $this->storeName($name);
    } else {
      $this->miss();
    }

    return $value;
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
  public function delete($name = '') {
    if ($name == '') {
      return $this->provider->flush();
    } else {
      return $this->provider->delete($this->prefix . $name);
    }
  }

  /**
   * Retrieves information of Cache state
   * 
   * @param bool $getFields
   *  
   * @return array
   */
  public function info($getFields = false) {
    $info = array();
    $className = explode('\\', get_class($this));
    $info['CACHE_TYPE'] = end($className);
    $info['CACHE_HITS'] = $this->hits;
    $info['CACHE_MISSES'] = $this->misses;

    $info = array_merge($info, call_user_func(array($this->provider, $this->infoMethod)));

    if ($getFields) {
      foreach ($this->fields as $field) {
        $info['field_content'][$field] = $this->get($field);
      }
    }

    return $info;
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
