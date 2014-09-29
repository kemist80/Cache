<?php

namespace Kemist\Cache\Storage;

/**
 * ApcObject
 *
 * @package Kemist\Cache
 * 
 * @version 1.0.1
 */
class ApcObject {

  /**
   * Gets a cached variable
   * 
   * @param string $name
   * 
   * @return mixed
   */
  public function get($name) {
    return apc_fetch($name);
  }

  /**
   * Flushes APC cache
   * 
   * @return bool
   */
  public function flush() {
    return apc_clear_cache('user');
  }

  /**
   * Deletes specified value from cache
   *    
   * @param string $name
   * 
   * @return bool
   */
  public function delete($name) {
    return apc_delete($name);
  }

  /**
   * Stores a value in cache
   * 
   * @param string $name   
   * @param mixed $val
   * 
   * @return bool
   */
  public function put($name, $val) {
    return apc_store($name, $val);
  }

  /**
   * Gets cache info
   * 
   * @return array
   */
  public function info() {
    return apc_cache_info('user');
  }

}
