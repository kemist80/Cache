<?php

namespace Kemist\Cache;

/**
 * Cache Info
 *
 * @version 1.0.3
 */
class Info implements \ArrayAccess, \IteratorAggregate {

  /**
   * Cache info data
   * @var array 
   */
  protected $data = array();

  /**
   * Constructor
   * 
   * @param array $data
   */
  public function __construct(array $data = array()) {
    $this->data = $data;
  }

  /**
   * ArrayAccess offsetExists
   * 
   * @param string $offset
   * @return bool
   */
  public function offsetExists($offset) {
    return isset($this->data[$offset]);
  }

  /**
   * ArrayAccess offsetGet
   * 
   * @param string $offset
   * @return mixed
   */
  public function offsetGet($offset) {
    return $this->data[$offset];
  }

  /**
   * ArrayAccess offsetSet
   * 
   * @param string $offset
   * @param mixed $value
   */
  public function offsetSet($offset, $value) {
    $this->data[$offset] = $value;
  }

  /**
   * ArrayAccess offsetUnset
   * 
   * @param string $offset
   */
  public function offsetUnset($offset) {
    unset($this->data[$offset]);
  }

  /**
   * IteratorAggregate
   *
   * @return \ArrayIterator
   */
  public function getIterator() {
    return new \ArrayIterator($this->data);
  }

  /**
   * Updates timestamp items in cache info
   * 
   * @param string $name
   * @param array|string $item_names
   */
  public function touchItem($name, $item_names = array()) {
    if (!is_array($item_names)) {
      $item_names = array($item_names);
    }
    foreach ($item_names as $item_name) {
      $this->data[$name][$item_name] = time();
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
    if (!isset($this->data[$name])) {
      return false;
    }
    switch ($type) {
      case 'date':
        return isset($this->data[$name][$item_name]) ? date($format, $this->data[$name][$item_name]) : null;
      case 'int':
        return (int) $this->getItemOrDefault($name, $item_name, 0);
      default:
        return $this->getItemOrDefault($name, $item_name);
    }
  }

  /**
   * Gets an item value or default if not exists
   * 
   * @param string $name
   * @param string $item_name
   * @param mixed $default
   * 
   * @return mixed
   */
  protected function getItemOrDefault($name, $item_name, $default = null) {
    return isset($this->data[$name][$item_name]) ? $this->data[$name][$item_name] : $default;
  }

  /**
   * Sets an item
   * 
   * @param string $name
   * @param string $item
   * @param mixed $value
   */
  public function setItem($name, $item, $value) {
    $this->data[$name][$item] = $value;
  }

  /**
   * Inreases an item in cache info
   * 
   * @param string $name
   * @param string $item_name
   */
  public function increaseItem($name, $item_name) {
    $this->data[$name][$item_name] = (isset($this->data[$name][$item_name]) && is_int($this->data[$name][$item_name]) ? ++$this->data[$name][$item_name] : 1);
  }

  /**
   * Gets Cache info data
   * 
   * @param $name Cache key
   * 
   * @return array
   */
  public function getData($name = '') {
    return $name == '' ? $this->data : (isset($this->data[$name]) ? $this->data[$name] : null);
  }

  /**
   * Gets all cache key names
   *  	 
   * @return array
   */
  public function getKeys() {
    return array_keys($this->data);
  }

  /**
   * Creates an info item
   * 
   * @param string $name
   */
  public function createData($name) {
    $this->data[$name] = array(
        'last_read' => null,
        'read_count' => 0,
        'tags' => array()
    );
    $this->touchItem($name, 'created');
  }

  /**
   * Deletes an info item
   * 
   * @param string $name
   */
  public function deleteData($name) {
    if (isset($this->data[$name])) {
      unset($this->data[$name]);
    }
  }

  /**
   * Appends data to an item
   * 
   * @param string $name
   * @param array $data
   */
  public function appendData($name, array $data) {
    $this->data[$name] = array_merge($this->data[$name], $data);
  }

  /**
   * Sets info data
   * 
   * @param array $data
   */
  public function setData(array $data = array()) {
    $this->data = $data;
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
    return isset($this->data[$name]['expiry']) ? ($this->data[$name]['expiry'] == 0 ? 0 : date($format, $this->data[$name]['expiry'])) : null;
  }

  /**
   * Get cache names having the given tags
   * 
   * @param array $tags
   * 
   * @return array
   */
  public function filterByTags(array $tags) {
    $ret = array();
    foreach ($this->data as $key => $info) {
      if (count(array_intersect($tags, $info['tags'])) > 0) {
        $ret[] = $key;
      }
    }
    return $ret;
  }

}
