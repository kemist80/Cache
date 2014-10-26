<?php

namespace Kemist\Cache;

use Kemist\Cache\Storage\StorageInterface;

/**
 * Cache manager object for caching variables
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.13
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
   * @var Info
   */
  protected $_info;

  /**
   * Read key names
   * @var array 
   */
  protected $_read_keys = array();
  
  /**
   * System reserved info key
   * @var string 
   */
  protected $_info_key='_system.info';

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
    $this->_info = new Info();
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

    if ($this->exist($this->_info_key)) {
      $this->_info->setData((array) $this->getOrPut($this->_info_key, array()));
      foreach ($this->_info as $key => $data) {
        if (!isset($data['expiry']) || $data['expiry'] == 0) {
          continue;
        } elseif (!$this->exist($key)) {
          unset($this->_info[$key]);
        } elseif (time() > $data['expiry']) {
          $this->clear($key);
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
    if (!$this->isEnabled()) {
      return false;
    }

    $this->init();
    return $this->_info->getData($name);
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
    return ($this->_storage->exist($secret) && ($name == $this->_info_key || isset($this->_info[$name])));
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

    if ($name == '') {
      $this->_info = new Info();
    } elseif (isset($this->_info[$name])) {
      unset($this->_info[$name]);
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
   * @param int|string $expiry Expires in the given seconds	(0:never) or the time defined by valid date string (eg. '2014-10-01' or '1week' or '2hours')
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
    if (false !== $ret = $this->_storage->put($secret, $data, $compressed)) {
      $expiry = ($expiry == 'never' ? 0 : $this->_extractExpiryDate($expiry));

      if (!isset($this->_info[$name])) {
        $this->_info->createItem($name);
      }

      $this->_info->touchItem($name, array('last_access', 'last_write'));
      $this->_info->appendData($name, array(
          'expiry' => $expiry,
          'size' => strlen($data),
          'compressed' => $compressed,
          'store_method' => $store_method,
      ));
      $this->_info->increaseItem($name, 'write_count');
    }

    return $ret;
  }

  /**
   * Extracts expiry by string
   * 
   * @param mixed $expiry
   * 
   * @return int
   */
  protected function _extractExpiryDate($expiry) {
    if (is_string($expiry)) {
      if (strtotime($expiry) === false) {
        throw new \InvalidArgumentException('Invalid date format!');
      }
      $date = new \DateTime($expiry);
      $expiry = $date->format('U') < time() ? 0 : $date->format('U');
    } elseif ((int) $expiry > 0) {
      $expiry = ($expiry < time() ? time() + $expiry : $expiry);
    } else {
      $expiry = 0;
    }

    return $expiry;
  }

  /**
   * Alias for storing a value in cache
   * 	 
   * @param string $name cache name
   * @param mixed $val variable to be stored
   * @param bool $compressed Compressed storage
   * @param int|string $expiry Expires in the given seconds	(0:never) or the time defined by valid date string (eg. '2014-10-01' or '1week' or '2hours')
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
   * @param int|string $expiry Expires in the given seconds	(0:never) or the time defined by valid date string (eg. '2014-10-01' or '1week' or '2hours')
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
   * @param mixed $default
   * 	 
   * @return mixed
   */
  public function get($name, $default = null) {
    if (!$this->isEnabled() || ($this->init() && $name != $this->_info_key && !isset($this->_info[$name]))) {
      $this->_storage->miss();
      return ($default instanceof \Closure ? call_user_func($default) : $default);
    }

    $compressed = ($name == $this->_info_key ? true : $this->_info->getItem($name, 'compressed'));
    $store_method = ($name == $this->_info_key ? self::STORE_METHOD_JSON : $this->_info->getItem($name, 'store_method'));
    $secret = $this->_encryptKey($name);
    $raw = $this->_storage->get($secret, $compressed);
    $ret = $this->_decode($raw, $store_method);

    $this->_info->touchItem($name, array('last_access', 'last_read'));
    $this->_info->increaseItem($name, 'read_count');

    if ($ret !== null) {
      $this->_read_keys[] = $name;
      array_unique($this->_read_keys);
    } elseif (isset($this->_info[$name])) {
      unset($this->_info[$name]);
    }

    return $ret;
  }

  /**
   * Attempts to get a value and if not exists store the given default variable
   * 
   * @param string $name cache name
   * @param mixed $default default value
   * @param bool $compressed Compressed storage
   * @param int|string $expiry Expires in the given seconds	(0:never) or the time defined by valid date string (eg. '2014-10-01' or '1week' or '2hours')
   * @param int $store_method Storing method (serialize|json)	 	 
   * 
   * @return mixed
   */
  public function getOrPut($name, $default, $compressed = false, $expiry = 0, $store_method = self::STORE_METHOD_SERIALIZE) {
    if ($this->exist($name)) {
      return $this->get($name);
    }
    $value = ($default instanceof \Closure ? call_user_func($default) : $default);
    $this->put($name, $value, $compressed, $expiry, $store_method);
    return $value;
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
   * @return array|bool
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
    return $this->_storage->getHits();
  }

  /**
   * Gets cache misses
   * 
   * @return int
   */
  public function getMisses() {
    if (!$this->isEnabled()) {
      return 0;
    }
    $this->init();
    return $this->_storage->getMisses();
  }

  /**
   * Stores cache values expiral information into cache
   */
  public function writeExpirals() {
    if (!$this->isEnabled() || $this->_initialised < 1) {
      return false;
    }
    return $this->put($this->_info_key, $this->_info->getData(), true, 0, self::STORE_METHOD_JSON);
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
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    return $this->_info->getExpiry($name, $format);
  }

  /**
   * Calculates Time To Live
   * 
   * @param string $name
   * 
   * @return int
   */
  public function getTTL($name) {
    $expiry = $this->getExpiry($name);
    return ($expiry > 0 ? (int) $expiry - (int) $this->getCreated($name) : 0);
  }

  /**
   * Modifies expiry by setting Time To Live
   * 
   * @param string $name
   * @param int $ttl
   */
  public function setTTL($name, $ttl) {
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    if ($this->exist($name)) {
      $created = (int) $this->getCreated($name);
      $ttl = (int) $ttl;
      $this->_info->setItem($name, 'expiry', ($ttl <= 0 ? 0 : $created + $ttl));
    }
  }

  /**
   * Modifies expiry
   * 
   * @param string $name
   * @param mixed $expiry
   */
  public function setExpiry($name, $expiry) {
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    if ($this->exist($name)) {
      $this->_info->setItem($name, 'expiry', $this->_extractExpiryDate($expiry));
    }
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
    return $this->_info->getItem($name, 'created', 'date', $format);
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
    return $this->_info->getItem($name, 'last_access', 'date', $format);
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
    return $this->_info->getItem($name, 'last_read', 'date', $format);
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
    return $this->_info->getItem($name, 'last_write', 'date', $format);
  }

  /**
   * Gets read count of a cached value
   * 
   * @param string $name Cache name
   * 	 
   * @return int
   */
  public function getReadCount($name) {
    return $this->_info->getItem($name, 'read_count', 'int');
  }

  /**
   * Gets write count of a cached value
   * 
   * @param string $name Cache name
   * 	 
   * @return int
   */
  public function getWriteCount($name) {
    return $this->_info->getItem($name, 'write_count', 'int');
  }

  /**
   * Gets all cache key names
   *  	 
   * @return array
   */
  public function getKeys() {
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    return $this->_info->getKeys();
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
