<?php

namespace Kemist\Cache;

use Kemist\Cache\Storage\StorageInterface;

/**
 * Cache manager object for caching variables
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.5
 */
class Manager {

  /**
   * Cache storage object
   * @var StorageInterface
   */
  protected $_storage;

  /**
   * Caching is enabled
   * @var bool 
   */
  protected $_enabled = true;

  /**
   * Key name encryption
   * @var bool 
   */
  protected $_encrypt_keys = true;

  /**
   * Cache values information
   * @var array
   */
  protected $_info = array();

  /**
   * Read key names
   * @var array 
   */
  protected $_read_keys = array();

  /**
   * Initialised (-1: not yet, 0: in progress, 1: initialised)
   * @var int 
   */
  protected $_initialised = -1;

  const STORE_METHOD_SERIALIZE = 1;
  const STORE_METHOD_JSON = 2;

  /**
   * Constructor 
   * 
   * @param StorageInterface $storage
   * @param array $options
   */
  public function __construct(StorageInterface $storage, array $options = array()) {
    $this->_storage = $storage;
    $this->_enabled = (isset($options['enabled']) ? $options['enabled'] : true);
    $this->_encrypt_keys = (isset($options['encrypt_keys']) ? $options['encrypt_keys'] : true);
  }

  /**
   * Initialise (lazy)
   */
  public function init() {
    if ($this->_initialised > -1) {
      return true;
    }

    // Initialising in progress 
    $this->_initialised = 0;
    $this->_storage->init();

    if ($this->exist('_system.info')) {
      $info = $this->get('_system.info');
      $this->_info = (is_array($info) ? $info : array());
      foreach ($this->_info as $key => $data) {
        if (!isset($data['expiry']) || $data['expiry'] == 0) {
          continue;
        }
        if ((time() > $data['expiry'] && $this->exist($key)) ||
                strlen($data['store_method']) == 0
        ) {
          $this->clear($key);
        }
        if (!$this->exist($key)) {
          unset($this->_info[$key]);
        }
      }
    }
    $this->_initialised = 1;
    return true;
  }

  /**
   * Gets Cache info
   * 
   * @param $name Cache key
   * 
   * @return array
   */
  public function getInfo($name = '') {
    return $name == '' ? $this->_info : (isset($this->_info[$name]) ? $this->_info[$name] : null);
  }

  /**
   * Check if Cache is enabled
   * 
   * @return bool
   */
  public function isEnabled() {
    return $this->_enabled;
  }

  /**
   * Enable/disable caching
   * 
   * @param bool $enabled
   */
  public function setEnabled($enabled) {
    $this->_enabled = (bool) $enabled;
  }

  /**
   * Checks if the specified name in cache exists
   * 	 
   * @param string $name cache name
   *
   * @return bool
   */
  public function exist($name) {
    if (!$this->isEnabled()) {
      return false;
    }

    $this->init();
    $secret = $this->_encryptKey($name);
    return ($this->_storage->exist($secret) && ($name == '_system.info' || isset($this->_info[$name])));
  }

  /**
   * Deletes the specified cache or each one if '' given
   * 	 
   * @param string $name cache name
   *
   * @return bool
   */
  public function clear($name = '') {
    if (!$this->isEnabled()) {
      return false;
    }

    $this->init();
    $secret = ($name != '' ? $this->_encryptKey($name) : $name);
    $ret = $this->_storage->clear($secret);

    if (isset($this->_info[$name])) {
      unset($this->_info[$name]);
    }

    if ($name == '') {
      $this->_info = array();
    }

    return $ret;
  }

  /**
   * Alias for deleting the specified cache or each one if '' given
   * 	 
   * @param string $name cache name
   *
   * @return bool
   */
  public function delete($name = '') {
    return $this->clear($name);
  }

  /**
   * Flush all from cache
   * 	 
   * @return bool
   */
  public function flush() {
    return $this->clear();
  }

  /**
   * Saves the variable to the $name cache
   * 	 
   * @param string $name cache name
   * @param mixed $val variable to be stored
   * @param bool $compressed Compressed storage
   * @param int $expiry Expires in the given seconds	(0:never) 
   * @param string $store_method Storing method (serialize|json)	 	 
   *
   * @return bool
   */
  public function put($name, $val, $compressed = false, $expiry = 0, $store_method = self::STORE_METHOD_SERIALIZE) {
    if (!$this->isEnabled()) {
      return false;
    }

    $this->init();
    $secret = $this->_encryptKey($name);
    $data = $this->_encode($val, $store_method);
    $ret = $this->_storage->put($secret, $data, $compressed);

    $read_count = (isset($this->_info[$name]['read_count']) ? $this->_info[$name]['read_count'] : 0);
    $write_count = (isset($this->_info[$name]['write_count']) ? $this->_info[$name]['write_count'] : 0);
    $created = (isset($this->_info[$name]['created']) ? $this->_info[$name]['created'] : time());
    $last_read = (isset($this->_info[$name]['last_read']) ? $this->_info[$name]['last_read'] : null);

    if (is_string($expiry)) {
      $expiry = $this->_extractExpiryDate($expiry);
    } elseif ((int) $expiry > 0) {
      $expiry = ($expiry < time() ? time() + $expiry : $expiry);
    } else {
      $expiry = 0;
    }

    $this->_info[$name] = array(
        'expiry' => $expiry,
        'size' => strlen($data),
        'compressed' => $compressed,
        'store_method' => $store_method,
        'created' => $created,
        'last_access' => time(),
        'last_read' => $last_read,
        'last_write' => time(),
        'read_count' => $read_count,
        'write_count' => ++$write_count
    );

    return $ret;
  }

  /**
   * Extracts expiry by string
   * 
   * @param string $expiry
   * 
   * @return int
   */
  protected function _extractExpiryDate($expiry) {
    if ($expiry == 'never') {
      return 0;
    }

    if (strtotime($expiry) === false) {
      throw new \InvalidArgumentException('Invalid date format!');
    }

    $date = new \DateTime($expiry);
    return $date->format('U') < time() ? 0 : $date->format('U');
  }

  /**
   * Alias for storing a value in cache
   * 	 
   * @param string $name cache name
   * @param mixed $val variable to be stored
   * @param bool $compressed Compressed storage
   * @param int $expiry Expires in the given seconds	(0:never) 
   * @param int $store_method Storing method (serialize|json)	 	 
   *
   * @return bool
   */
  public function set($name, $val, $compressed = false, $expiry = 0, $store_method = self::STORE_METHOD_SERIALIZE) {
    return $this->put($name, $val, $compressed, $expiry, $store_method);
  }

  /**
   * Alias for storing a value in cache
   * 	 
   * @param string $name cache name
   * @param mixed $val variable to be stored
   * @param bool $compressed Compressed storage
   * @param int $expiry Expires in the given seconds	(0:never) 
   * @param int $store_method Storing method (serialize|json)	 	 
   *
   * @return bool
   */
  public function store($name, $val, $compressed = false, $expiry = 0, $store_method = self::STORE_METHOD_SERIALIZE) {
    return $this->put($name, $val, $compressed, $expiry, $store_method);
  }

  /**
   * Retrieves the content of $name cache
   * 	 
   * @param string $name cache name
   * 	 
   * @return mixed
   */
  public function get($name) {
    if (!$this->isEnabled() || ($this->init() && $name != '_system.info' && !isset($this->_info[$name]))) {
      return null;
    }

    $compressed = ($name == '_system.info' ? true : $this->_info[$name]['compressed']);
    $store_method = ($name == '_system.info' ? self::STORE_METHOD_JSON : $this->_info[$name]['store_method']);
    $secret = $this->_encryptKey($name);
    $raw = $this->_storage->get($secret, $compressed);
    $ret = $this->_decode($raw, $store_method);

    $this->_info[$name]['last_access'] = time();
    $this->_info[$name]['last_read'] = time();

    $this->_info[$name]['read_count'] = (isset($this->_info[$name]['read_count']) ? ++$this->_info[$name]['read_count'] : 1);

    if ($ret !== null) {
      $this->_read_keys[] = $name;
      array_unique($this->_read_keys);
    } elseif (isset($this->_info[$name])) {
      unset($this->_info[$name]);
    }

    return $ret;
  }

  /**
   * Alias for retrieving the content of $name cache
   * 	 
   * @param string $name cache name
   * 	 
   * @return mixed
   */
  public function retrieve($name) {
    return $this->get($name);
  }

  /**
   * Alias for retrieving the content of $name cache
   * 	 
   * @param string $name cache name
   * 	 
   * @return mixed
   */
  public function load($name) {
    return $this->get($name);
  }

  /**
   * Retrieves information of Cache state
   * 
   * @param bool $get_fields
   * 	 
   * @return array
   */
  public function info($get_fields = false) {
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    return $this->_storage->info($get_fields);
  }

  /**
   * Encodes variable with the specified method
   * 
   * @param mixed $var Variable
   * @param int $store_method serialize|json	 	 	 
   * 	 
   * @return mixed
   */
  protected function _encode($var, $store_method = self::STORE_METHOD_SERIALIZE) {
    switch ($store_method) {
      case self::STORE_METHOD_JSON:
        $var = json_encode($var);
        break;
      case self::STORE_METHOD_SERIALIZE:
      default:
        $var = serialize($var);
    }
    return $var;
  }

  /**
   * Decodes variable with the specified method
   * 
   * @param mixed $var Variable
   * @param int $store_method serialize|json	 	 	 
   * 	 
   * @return mixed
   */
  protected function _decode($var, $store_method = self::STORE_METHOD_SERIALIZE) {
    if (!$var) {
      return null;
    }

    switch ($store_method) {
      case self::STORE_METHOD_JSON:
        $var = json_decode($var, true);
        break;
      case self::STORE_METHOD_SERIALIZE:
      default:
        $var = unserialize($var);
    }

    return $var;
  }

  /**
   * Encrypts key
   * 
   * @param string $key
   * 
   * @return string
   */
  protected function _encryptKey($key) {
    return ($this->_encrypt_keys ? sha1($key) : $key);
  }

  /**
   * Gets cache hits
   * 
   * @return int
   */
  public function getHits() {
    if (!$this->isEnabled()) {
      return 0;
    }
    $this->init();
    return $this->_storage->hits;
  }

  /**
   * Stores cache values expiral information into cache
   */
  public function writeExpirals() {
    if (!$this->isEnabled() || $this->_initialised < 1) {
      return false;
    }
    return $this->put('_system.info', $this->_info, true, 0, self::STORE_METHOD_JSON);
  }

  /**
   * Gets expiry information of a cached value (0: never)
   * 
   * @param string $name Cache name
   * @param string $format Date format
   * 	 
   * @return string
   */
  public function getExpiry($name, $format = 'U') {
    $this->init();
    if (!isset($this->_info[$name])) {
      return false;
    }
    return isset($this->_info[$name]['expiry']) ? ($this->_info[$name]['expiry'] == 0 ? 0 : date($format, $this->_info[$name]['expiry'])) : null;
  }

  /**
   * Gets created (first write) time of a cached value
   * 
   * @param string $name Cache name
   * @param string $format Date format
   * 	 
   * @return string
   */
  public function getCreated($name, $format = 'U') {
    return $this->_getDateInfo($name, 'created', $format);
  }

  /**
   * Gets last access (either read or write) time of a cached value
   * 
   * @param string $name Cache name
   * @param string $format Date format
   * 	 
   * @return string
   */
  public function getLastAccess($name, $format = 'U') {
    return $this->_getDateInfo($name, 'last_access', $format);
  }

  /**
   * Gets last read time of a cached value
   * 
   * @param string $name Cache name
   * @param string $format Date format
   * 	 
   * @return string
   */
  public function getLastRead($name, $format = 'U') {
    return $this->_getDateInfo($name, 'last_read', $format);
  }

  /**
   * Gets last write time of a cached value
   * 
   * @param string $name Cache name
   * @param string $format Date format
   * 	 
   * @return string
   */
  public function getLastWrite($name, $format = 'U') {
    return $this->_getDateInfo($name, 'last_write', $format);
  }

  /**
   * Gets a date typed parameter from cache info
   * 
   * @param string $name
   * @param string $param_name
   * @param string $format
   * 
   * @return string
   */
  protected function _getDateInfo($name, $param_name, $format = 'U') {
    $this->init();
    if (!isset($this->_info[$name])) {
      return false;
    }
    return isset($this->_info[$name][$param_name]) ? date($format, $this->_info[$name][$param_name]) : null;
  }
  
  /**
   * Gets an integer typed parameter from cache info
   * 
   * @param string $name
   * @param string $param_name
   * 
   * @return int
   */
  protected function _getIntegerInfo($name, $param_name) {
    $this->init();
    if (!isset($this->_info[$name])) {
      return false;
    }
    return isset($this->_info[$name][$param_name]) ? (int)$this->_info[$name][$param_name] : 0;
  }

  /**
   * Gets read count of a cached value
   * 
   * @param string $name Cache name
   * 	 
   * @return int
   */
  public function getReadCount($name) {
    return $this->_getIntegerInfo($name,'read_count');
  }

  /**
   * Gets write count of a cached value
   * 
   * @param string $name Cache name
   * 	 
   * @return int
   */
  public function getWriteCount($name) {
    return $this->_getIntegerInfo($name,'write_count');
  }

  /**
   * Gets all cache key names
   *  	 
   * @return array
   */
  public function getKeys() {
    $this->init();
    return array_keys($this->_info);
  }

  /**
   * Gets cache key names which already read
   *  	 
   * @return array
   */
  public function getReadKeys() {
    return $this->_read_keys;
  }

  /**
   * Gets storage object
   * 
   * @return StorageInterface
   */
  public function getStorage() {
    return $this->_storage;
  }

  /**
   * Retrieves key encryption
   * 
   * @return bool
   */
  public function getEncryptKeys() {
    return $this->_encrypt_keys;
  }

  /**
   * Sets key encryption
   * 
   * @param bool $encrypt_keys
   */
  public function setEncryptKeys($encrypt_keys) {
    $this->_encrypt_keys = (bool) $encrypt_keys;
  }

  /**
   * Sets cache storage
   * 
   * @param StorageInterface $storage
   */
  public function setStorage(StorageInterface $storage) {
    $this->_storage = $storage;
  }

  /**
   * Destructor
   */
  public function __destruct() {
    $this->writeExpirals();
  }

}
