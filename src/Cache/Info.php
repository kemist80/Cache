<?php

namespace Kemist\Cache;

/**
 * Cache Info
 *
 * @version 1.0.0
 */
class Info implements \ArrayAccess, \IteratorAggregate {

  /**
   * Cache info data
   * @var array 
   */
  protected $_data = array();

  /**
   * Constructor
   * 
   * @param array $data
   */
  public function __construct(array $data = array()) {
    $this->_data = $data;
  }

  /**
   * ArrayAccess offsetExists
   * 
   * @param string $offset
   * @return bool
   */
  public function offsetExists($offset) {
    return isset($this->_data[$offset]);
  }

  /**
   * ArrayAccess offsetGet
   * 
   * @param string $offset
   * @return mixed
   */
  public function offsetGet($offset) {
    return $this->_data[$offset];
  }

  /**
   * ArrayAccess offsetSet
   * 
   * @param string $offset
   * @param mixed $value
   */
  public function offsetSet($offset, $value) {
    $this->_data[$offset] = $value;
  }

  /**
   * ArrayAccess offsetUnset
   * 
   * @param string $offset
   */
  public function offsetUnset($offset) {
    unset($this->_data[$offset]);
  }

  /**
   * IteratorAggregate
   *
   * @return \ArrayIterator
   */
  public function getIterator() {
    return new \ArrayIterator($this->_data);
  }

  /**
   * Updates timestamp items in cache info
   * 
   * @param string $name
   * @param array $item_names
   */
  public function touchItem($name, $item_names = array()) {
    if (!is_array($item_names)) {
      $item_names = array($item_names);
    }
    foreach ($item_names as $item_name) {
      $this->_data[$name][$item_name] = time();
    }
  }

  /**
   * Gets an item from cache info
   * 
   * @param string $name
   * @param string $item_name
   * @param string $type
   * @param string $format
   * 
   * @return string|int|bool
   */
  public function getItem($name, $item_name, $type = 'int', $format = 'U') {
    if (!isset($this->_data[$name])) {
      return false;
    }
    switch ($type) {
      case 'date':
        return isset($this->_data[$name][$item_name]) ? date($format, $this->_data[$name][$item_name]) : null;
      case 'int':
        return isset($this->_data[$name][$item_name]) ? (int) $this->_data[$name][$item_name] : 0;
      default:
        return isset($this->_data[$name][$item_name]) ? $this->_data[$name][$item_name] : null;
    }
  }
  
  /**
   * Sets an item
   * 
   * @param string $name
   * @param string $item
   * @param mixed $value
   */
  public function setItem($name,$item,$value){
    $this->_data[$name][$item]=$value;
  }

  /**
   * Inreases an item in cache info
   * 
   * @param string $name
   * @param string $item_name
   */
  public function increaseItem($name, $item_name) {
    $this->_data[$name][$item_name] = (isset($this->_data[$name][$item_name]) && is_int($this->_data[$name][$item_name]) ? ++$this->_data[$name][$item_name] : 1);
  }

  /**
   * Gets Cache info data
   * 
   * @param $name Cache key
   * 
   * @return array
   */
  public function getData($name = '') {
    return $name == '' ? $this->_data : (isset($this->_data[$name]) ? $this->_data[$name] : null);
  }

  /**
   * Gets all cache key names
   *  	 
   * @return array
   */
  public function getKeys() {
    return array_keys($this->_data);
  }

  /**
   * Creates an info item
   * 
   * @param string $name
   */
  public function createItem($name) {
    $this->_data[$name] = array(
        'last_read' => null,
        'read_count' => 0
    );
    $this->touchItem($name,'created');
  }

  /**
   * Appends data to an item
   * 
   * @param string $name
   * @param array $data
   */
  public function appendData($name, array $data) {
    $this->_data[$name] = array_merge($this->_data[$name], $data);
  }

  /**
   * Sets info data
   * 
   * @param array $data
   */
  public function setData(array $data = array()) {
    $this->_data = $data;
  }
  
  /**
   * Gets expiry information
   * 
   * @param string $name Cache name
   * @param string $format Date format
   * 	 
   * @return string
   */
  public function getExpiry($name, $format = 'U') {
    return isset($this->_data[$name]['expiry']) ? ($this->_data[$name]['expiry'] == 0 ? 0 : date($format, $this->_data[$name]['expiry'])) : null;
  }  

}
