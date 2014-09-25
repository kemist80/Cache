<?php

namespace Kemist\Cache\Storage;

/**
 * Apc Storage
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.2
 */
class Apc implements StorageInterface {

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
  protected $_prefix = '';
  
  /**
   * APC Object
   * @var ApcObject 
   */
  protected $_apc;

  /**
   * Constructor
   * 
   * @param array $options
   */
  public function __construct(ApcObject $apc, array $options = array()) {
    $this->_prefix = (isset($options['prefix']) ? $options['prefix'] : '');
    $this->_apc=$apc;
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
    if ($this->_apc->get($this->_prefix . $name)) {
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
      return $this->_apc->flush();
    } else {
      return $this->_apc->clear($this->_prefix . $name);
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
    $ret = $this->_apc->put($this->_prefix . $name, $val);
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
    $ret = $this->_apc->get($this->_prefix . $name);

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
    $ret['CACHE_TYPE'] = 'APC';
    $ret['CACHE_HITS'] = $this->_hits;
    $ret = array_merge($ret, $this->_apc->info());

    if ($get_fields) {
      foreach ($this->_fields as $field) {
        $ret['field_content'][$field] = $this->get($field);
      }
    }

    return $ret;
  }

}

