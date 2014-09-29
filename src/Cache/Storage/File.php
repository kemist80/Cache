<?php

namespace Kemist\Cache\Storage;

/**
 * File Storage object for file based cache
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.9
 */
class File extends Service implements StorageInterface {

  /**
   * Cache file extension
   * 	 	
   * @var int
   */
  protected $_extension = 'kcf';

  /**
   * Cache file locking
   * 	 	
   * @var bool
   */
  protected $_file_locking = true;

  /**
   *
   * @var type 
   */
  protected $_cache_dir;

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
    $this->_cache_dir = $options['cache_dir'];
    $this->_extension = (isset($options['extension']) ? $options['extension'] : 'kcf');
    $this->_file_locking = (isset($options['file_locking']) ? $options['file_locking'] : true);
  }

  /**
   * Initialise Cache storage
   * 
   * @return boolean
   * 
   * @throws \Kemist\Cache\Exception
   */
  public function init() {
    if (!is_writable($this->_cache_dir)) {
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
    return file_exists($this->_cache_dir . '.' . $name . '.' . $this->_extension);
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
      foreach ($this->_getAllCacheFiles() as $file) {
        unlink($this->_cache_dir . $file);        
      }
      return true;
    } elseif (file_exists($this->_cache_dir . '.' . $name . '.' . $this->_extension)) {
      unlink($this->_cache_dir . '.' . $name . '.' . $this->_extension);
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
    $f = fopen($this->_cache_dir . '.' . $name . '.' . $this->_extension, 'wb');
    $ret = false;
    if ($f) {      
      if (false !== $success = $this->_lockFile($f,true)) {
        $ret = ($compressed ? fputs($f, gzcompress($val)) : fputs($f, $val));
        $ret ? $this->_storeName($name) : null;
        $this->_unlockFile($f, $success);
      }

      fclose($f);
      $ret&=$success;
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
    $ret = false;

    $filename = $this->_cache_dir . '.' . $name . '.' . $this->_extension;
    if (!file_exists($filename)) {
      return false;
    }

    if (false !== $f=fopen($filename, "rb")) {
      $success = $this->_lockFile($f);
      $temp = '';
      while ($success && !feof($f)) {
        $temp .= fread($f, 8192);
      }
      $this->_unlockFile($f, $success);
      fclose($f);
      $this->_hits++;
      $ret = ($compressed ? gzuncompress($temp) : $temp);
      $ret ? $this->_storeName($name) : null;
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
  protected function _lockFile($f,$write=false){
    if (!$this->_file_locking){
      return true;
    }
    return ($write ? flock($f, LOCK_EX) : flock($f, LOCK_SH));
  }
  
  /**
   * Unlocks file
   * 
   * @param resource $f
   * @param bool $locked
   * 
   * @return bool
   */
  protected function _unlockFile($f, $locked) {    
    if (!$this->_file_locking || !$locked){
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
    $ret['CACHE_HITS'] = $this->_hits;
    $fields = array();
    
    foreach ($this->_getAllCacheFiles() as $file) {
      $name = basename($file, '.' . $this->_extension);
      $ret[$name]['size'] = filesize(($this->_cache_dir . $file));
      $ret[$name]['last_modified'] = date('Y.m.d. H:i:s', filemtime($this->_cache_dir . $file));
      $ret[$name]['last_accessed'] = date('Y.m.d. H:i:s', fileatime($this->_cache_dir . $file));
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
  protected function _getAllCacheFiles(){
    $files=array();
    foreach (scandir($this->_cache_dir) as $file) {
      $temp = explode('.', $file);
      if (array_pop($temp) == $this->_extension) {
        $files[]=$file;
      }
    }
    return $files;
  }

}
