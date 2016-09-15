<?php

namespace Kemist\Cache\Storage;

/**
 * FileStorage object for file based cache
 * 
 * @package Kemist\Cache
 * 
 * @version 1.1.4
 */
class FileStorage extends AbstractStorage implements StorageInterface {

  /**
   * Cache file extension
   * @var int
   */
  protected $extension = 'kcf';

  /**
   * Cache file locking
   * @var bool
   */
  protected $fileLocking = true;

  /**
   * Cache directory
   * @var type 
   */
  protected $cacheDir;

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
    $this->cacheDir = $options['cache_dir'];
    $this->extension = (isset($options['extension']) ? $options['extension'] : 'kcf');
    $this->fileLocking = (isset($options['file_locking']) ? $options['file_locking'] : true);
  }

  /**
   * Initialise Cache storage
   * 
   * @return boolean
   * 
   * @throws \Kemist\Cache\Exception
   */
  public function init() {
    if (!is_writable($this->cacheDir)) {
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
  public function has($name) {
    return file_exists($this->cacheDir . '.' . $name . '.' . $this->extension);
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
      foreach ($this->getAllCacheFiles() as $file) {
        unlink($this->cacheDir . $file);
      }
      return true;
    } elseif (file_exists($this->cacheDir . '.' . $name . '.' . $this->extension)) {
      unlink($this->cacheDir . '.' . $name . '.' . $this->extension);
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
  public function store($name, $val, $compressed = false) {
    $success = false;

    if (false !== $handle = fopen($this->cacheDir . '.' . $name . '.' . $this->extension, 'wb')) {
      if ($this->lockFile($handle, true)) {
        $success = fputs($handle, ($compressed ? gzcompress($val) : $val));
        $this->unlockFile($handle);
      }

      fclose($handle);
      $success ? $this->storeName($name) : null;
    }

    return $success;
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
    $fileName = $this->cacheDir . '.' . $name . '.' . $this->extension;
    if (!file_exists($fileName)) {
      $this->miss();
      return false;
    }

    $success = false;
    if (false !== $handle = fopen($fileName, "rb")) {
      if ($this->lockFile($handle)) {
        $temp = '';
        while (!feof($handle)) {
          $temp .= fread($handle, 8192);
        }
        $this->unlockFile($handle);
        $this->hit();
        $success = ($compressed ? gzuncompress($temp) : $temp);
      }
      fclose($handle);
      $success ? $this->storeName($name) : null;
    }

    return $success;
  }

  /**
   * Locks file
   * 
   * @param resource $handle
   * @param bool $write
   * 
   * @return bool
   */
  protected function lockFile($handle, $write = false) {
    if (!$this->fileLocking) {
      return true;
    }
    return ($write ? flock($handle, LOCK_EX) : flock($handle, LOCK_SH));
  }

  /**
   * Unlocks file
   * 
   * @param resource $handle
   * 
   * @return bool
   */
  protected function unlockFile($handle) {
    if (!$this->fileLocking) {
      return true;
    }
    return flock($handle, LOCK_UN);
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
    $info['CACHE_TYPE'] = 'File';
    $info['CACHE_HITS'] = $this->hits;
    $info['CACHE_MISSES'] = $this->misses;
    $fields = array();

    foreach ($this->getAllCacheFiles() as $file) {
      $name = basename($file, '.' . $this->extension);
      $info[$name]['size'] = filesize(($this->cacheDir . $file));
      $info[$name]['last_modified'] = date('Y.m.d. H:i:s', filemtime($this->cacheDir . $file));
      $info[$name]['last_accessed'] = date('Y.m.d. H:i:s', fileatime($this->cacheDir . $file));
      $fields[] = $name;
    }

    if ($getFields) {
      foreach ($fields as $field) {
        $field = substr($field, 1);
        $info['field_content'][$field] = $this->get($field);
      }
    }

    return $info;
  }

  /**
   * Gets all cache files
   * 
   * @return array
   */
  protected function getAllCacheFiles() {
    $files = array();
    foreach (scandir($this->cacheDir) as $file) {
      $temp = explode('.', $file);
      if (array_pop($temp) == $this->extension) {
        $files[] = $file;
      }
    }
    return $files;
  }

}
