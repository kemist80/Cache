<?php

namespace Kemist\Cache\Storage;

/**
 * Abstract Service
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.0
 */
abstract class Service {

  /**
   * Retrieves the content of $name cache
   * 	 
   * @param string $name cache name
   * @param bool $compressed
   *
   * @return mixed
   */
  public function get($name, $compressed = false) {
    $ret = $this->_service->get($this->_prefix . $name);
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
    $classname = explode('\\', get_class($this));
    $ret['CACHE_TYPE'] = end($classname);
    $ret['CACHE_HITS'] = $this->_hits;

    $ret = array_merge($ret, call_user_func(array($this->_service, $this->_info_method)));

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

}
