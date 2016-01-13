<?php

namespace Kemist\Cache\Storage;

/**
 * FileStorage object for file based cache
 * 
 * @package Kemist\Cache
 * 
 * @version 1.1.3
 */
class FileStorage extends AbstractStorage implements StorageInterface {

  /**
   * Cache file extension
   * 	 	
   * @var int
   */
  protected $extension = 'kcf';

  /**
   * Cache file locking
   * 	 	
   * @var bool
   */
  protected $file_locking = true;

  /**
   *
   * @var type 
   */
  protected $cache_dir;

  /**
   * Constructor
   * 
   * @param array $options
   */
  public function __construct(array $options = array()) {
    if (!isset($options['cache_dir'])) {
      $options['cache_dir'] = sys_get_temp_dir() . '/kemist_cache/';
      if (!is_dir($options['cache_dir'])) {
        mkdir($options['cache_dir']);
      }
    }
    if (substr($options['cache_dir'], -1, 1) != '/') {
      $options['cache_dir'].='/';
    }
    $this->cache_dir = $options['cache_dir'];
    $this->extension = (isset($options['extension']) ? $options['extension'] : 'kcf');
    $this->file_locking = (isset($options['file_locking']) ? $options['file_locking'] : true);
  }

  /**
   * Initialise Cache storage
   * 
   * @return boolean
   * 
   * @throws \Kemist\Cache\Exception
   */
  public function init() {
    if (!is_writable($this->cache_dir)) {
      throw new \Kemist\Cache\Exception("Cache directory is not writable!");
    }
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
    return file_exists($this->cache_dir . '.' . $name . '.' . $this->extension);
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
      foreach ($this->getAllCacheFiles() as $file) {
        unlink($this->cache_dir . $file);
      }
      return true;
    } elseif (file_exists($this->cache_dir . '.' . $name . '.' . $this->extension)) {
      unlink($this->cache_dir . '.' . $name . '.' . $this->extension);
      return true;
    }

    return false;
  }

  /**
   * Saves the variable to the $name cache
   * 	 
   * @param string $name cache name
   * @param mixed $val variable to be stored
   * @param bool $compressed Compress value with gz
   *
   * @return bool
   */
  public function put($name, $val, $compressed = false) {
    $ret = false;

    if (false !== $f = fopen($this->cache_dir . '.' . $name . '.' . $this->extension, 'wb')) {
      if ($this->lockFile($f, true)) {
        $ret = fputs($f, ($compressed ? gzcompress($val) : $val));
        $this->unlockFile($f);
      }

      fclose($f);
      $ret ? $this->storeName($name) : null;
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
    $filename = $this->cache_dir . '.' . $name . '.' . $this->extension;
    if (!file_exists($filename)) {
      $this->miss();
      return false;
    }

    $ret = false;
    if (false !== $f = fopen($filename, "rb")) {
      if ($this->lockFile($f)) {
        $temp = '';
        while (!feof($f)) {
          $temp .= fread($f, 8192);
        }
        $this->unlockFile($f);
        $this->hit();
        $ret = ($compressed ? gzuncompress($temp) : $temp);
      }
      fclose($f);
      $ret ? $this->storeName($name) : null;
    }

    return $ret;
  }

  /**
   * Locks file
   * 
   * @param resource $f
   * @param bool $write
   * 
   * @return bool
   */
  protected function lockFile($f, $write = false) {
    if (!$this->file_locking) {
      return true;
    }
    return ($write ? flock($f, LOCK_EX) : flock($f, LOCK_SH));
  }

  /**
   * Unlocks file
   * 
   * @param resource $f
   * 
   * @return bool
   */
  protected function unlockFile($f) {
    if (!$this->file_locking) {
      return true;
    }
    return flock($f, LOCK_UN);
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
    $ret['CACHE_TYPE'] = 'File';
    $ret['CACHE_HITS'] = $this->hits;
    $ret['CACHE_MISSES'] = $this->misses;
    $fields = array();

    foreach ($this->getAllCacheFiles() as $file) {
      $name = basename($file, '.' . $this->extension);
      $ret[$name]['size'] = filesize(($this->cache_dir . $file));
      $ret[$name]['last_modified'] = date('Y.m.d. H:i:s', filemtime($this->cache_dir . $file));
      $ret[$name]['last_accessed'] = date('Y.m.d. H:i:s', fileatime($this->cache_dir . $file));
      $fields[] = $name;
    }

    if ($get_fields) {
      foreach ($fields as $field) {
        $field = substr($field, 1);
        $ret['field_content'][$field] = $this->get($field);
      }
    }

    return $ret;
  }

  /**
   * Gets all cache files
   * 
   * @return array
   */
  protected function getAllCacheFiles() {
    $files = array();
    foreach (scandir($this->cache_dir) as $file) {
      $temp = explode('.', $file);
      if (array_pop($temp) == $this->extension) {
        $files[] = $file;
      }
    }
    return $files;
  }

}
