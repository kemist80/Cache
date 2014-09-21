<?php

namespace Kemist\Cache\Storage;

/**
 * File Storage object for file based cache
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.0
 */
class File implements StorageInterface {

  /**
   * Number of hits
   * 	 	
   * @var int
   */
  protected $_hits = 0;

  /**
   * Cached field names
   * 	 	
   * @var array
   */
  protected $_fields = array();

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
   * Check if $name cache exists and not older than $max_age
   * 	 
   * @param string $name cache name
   * @param int $max_age cache max lifetime
   *
   * @return bool
   */
  public function exist($name, $max_age = 0) {
    if (
            file_exists($this->_cache_dir . '.' . $name . '.' . $this->_extension) &&
            ($max_age == 0 || date('U') - filemtime($this->_cache_dir . '.' . $name . '.' . $this->_extension) <= $max_age)
    ) {
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
      if ($dir = opendir($this->_cache_dir)) {
        while (false !== ($file = readdir($dir))) {
          if (
                  $file != "." &&
                  $file != ".." &&
                  $file != '.htaccess'
          ) {
            unlink($this->_cache_dir . $file);
          }
        }
        closedir($dir);
        return false;
      }
    } else {

      if (file_exists($this->_cache_dir . '.' . $name . '.' . $this->_extension)) {
        unlink($this->_cache_dir . '.' . $name . '.' . $this->_extension);
        return true;
      }
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
    if ($f) {
      $success = true;
      if ($this->_file_locking) {
        $success = flock($f, LOCK_EX);
      }
      if ($success) {
        if ($compressed) {
          $ret = fputs($f, gzcompress(($val)));
        } else {
          $ret = fputs($f, ($val));
        }
        if ($ret && !in_array($name, $this->_fields)) {
          $this->_fields[] = $name;
        }
      }

      if ($this->_file_locking && $success) {
        flock($f, LOCK_UN);
      }
      fclose($f);
      return ($ret && $success);
    }

    return false;
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

    $f = fopen($filename, "rb");
    if ($f) {
      $success = true;
      if ($this->_file_locking) {
        $success = flock($f, LOCK_SH);
      }

      $temp = '';
      while ($success && !feof($f)) {
        $temp .= fread($f, 8192);
      }
      if ($this->_file_locking && $success) {
        flock($f, LOCK_UN);
      }
      fclose($f);
      $this->_hits++;
      if ($compressed) {
        $ret = (gzuncompress($temp));
      } else {
        $ret = ($temp);
      }
    }

    if ($ret != false) {
      if (!in_array($name, $this->_fields)) {
        $this->_fields[] = $name;
      }
    }

    return $ret;
  }

  /**
   * Retrieves information of Cache state
   * 	 
   * @return array
   */
  public function info($get_fields = false) {
    $ret = array();
    $ret['CACHE_TYPE'] = 'File';
    $ret['CACHE_HITS'] = $this->_hits;
    $fields = array();

    if ($dir = opendir($this->_cache_dir)) {
      while (false !== ($file = readdir($dir))) {
        if (
                $file != "." &&
                $file != ".." &&
                $file != '.htaccess'
        ) {
          $name = basename($file, '.' . $this->_extension);
          $ret[$name]['size'] = filesize(($this->_cache_dir . $file));
          $ret[$name]['last_modified'] = date('Y.m.d. H:i:s', filemtime($this->_cache_dir . $file));
          $ret[$name]['last_accessed'] = date('Y.m.d. H:i:s', fileatime($this->_cache_dir . $file));
          $fields[] = $name;
        }
      }
      closedir($dir);
    }

    if ($get_fields) {
      foreach ($fields as $field) {
        $field = substr($field, 1);
        $ret['field_content'][$field] = $this->get($field);
      }
    }

    return $ret;
  }

}

?>
