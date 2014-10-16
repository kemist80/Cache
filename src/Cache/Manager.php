<?php

namespace Kemist\Cache;

use Kemist\Cache\Storage\StorageInterface;

/**
 * Cache manager object for caching variables
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.11
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
      $this->_info=(array)$this->getOrPut('_system.info',array());
      foreach ($this->_info as $key => $data) {
        if (!isset($data['expiry']) || $data['expiry'] == 0) {
          continue;
        }elseif (!$this->exist($key)) {
          unset($this->_info[$key]);
        }elseif (time() > $data['expiry']) {
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

    if ($name == '') {
      $this->_info = array();
    }elseif (isset($this->_info[$name])) {
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
    if (false !== $ret = $this->_storage->put($secret, $data, $compressed)){            
      $expiry = ($expiry == 'never' ? 0 : $this->_extractExpiryDate($expiry));
      
      if (!isset($this->_info[$name])){
          $this->_info[$name] = array(
            'created' => time(),
            'last_read' => null,
            'read_count' => 0
        );
      }
                
      $this->_touchInfoItem($name, array('last_access','last_write'));
      $this->_info[$name]['expiry'] = $expiry;
      $this->_info[$name]['size'] = strlen($data);
      $this->_info[$name]['compressed'] = $compressed;
      $this->_info[$name]['store_method'] = $store_method;
      $this->_increaseInfoItem($name, 'write_count');
      
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
      $expiry=$date->format('U') < time() ? 0 : $date->format('U');
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
  public function get($name, $default=null) {
    if (!$this->isEnabled() || ($this->init() && $name != '_system.info' && !isset($this->_info[$name]))) {
      return ($default instanceof \Closure ? call_user_func($default) : $default);
    }

    $compressed = ($name == '_system.info' ? true : $this->_info[$name]['compressed']);
    $store_method = ($name == '_system.info' ? self::STORE_METHOD_JSON : $this->_info[$name]['store_method']);
    $secret = $this->_encryptKey($name);
    $raw = $this->_storage->get($secret, $compressed);
    $ret = $this->_decode($raw, $store_method);

    $this->_touchInfoItem($name, array('last_access','last_read'));
    $this->_increaseInfoItem($name, 'read_count');

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
  public function getOrPut($name,$default,$compressed = false, $expiry = 0, $store_method = self::STORE_METHOD_SERIALIZE){
    if ($this->exist($name)){
      return $this->get($name);
    }
    $value=($default instanceof \Closure ? call_user_func($default) : $default);
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
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    if (!isset($this->_info[$name])) {
      return false;
    }
    return isset($this->_info[$name]['expiry']) ? ($this->_info[$name]['expiry'] == 0 ? 0 : date($format, $this->_info[$name]['expiry'])) : null;
  }
  
  /**
   * Calculates Time To Live
   * 
   * @param string $name
   * 
   * @return int
   */
  public function getTTL($name){
    $expiry=$this->getExpiry($name);
    return ($expiry > 0 ? (int)$expiry - (int)$this->getCreated($name) : 0);
  }
  
  /**
   * Modifies expiry by setting Time To Live
   * 
   * @param string $name
   * @param int $ttl
   */
  public function setTTL($name, $ttl){   
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    if ($this->exist($name)){
      $created=(int)$this->getCreated($name);
      $ttl=(int)$ttl;
      $this->_info[$name]['expiry']=($ttl<=0 ? 0 : $created+$ttl);
    }
  }
  
  /**
   * Modifies expiry
   * 
   * @param string $name
   * @param mixed $expiry
   */
  public function setExpiry($name, $expiry){ 
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    if ($this->exist($name)){
      $this->_info[$name]['expiry']=$this->_extractExpiryDate($expiry);
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
    return $this->_getInfoItem($name, 'created', 'date', $format);
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
    return $this->_getInfoItem($name, 'last_access', 'date', $format);
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
    return $this->_getInfoItem($name, 'last_read', 'date', $format);
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
    return $this->_getInfoItem($name, 'last_write', 'date', $format);
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
  protected function _getInfoItem($name, $item_name, $type = 'int', $format = 'U') {
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    if (!isset($this->_info[$name])) {
      return false;
    }
    switch ($type) {      
      case 'date':
        return isset($this->_info[$name][$item_name]) ? date($format, $this->_info[$name][$item_name]) : null;
      case 'int':
      default:
        return isset($this->_info[$name][$item_name]) ? (int) $this->_info[$name][$item_name] : 0;
    }
  }
  
  /**
   * Inreases an item in cache info
   * 
   * @param string $name
   * @param string $item_name
   */
  protected function _increaseInfoItem($name,$item_name){
    $this->_info[$name][$item_name] = (isset($this->_info[$name][$item_name]) && is_int($this->_info[$name][$item_name]) ? ++$this->_info[$name][$item_name] : 1);
  }
  
  /**
   * Updates timestamp items in cache info
   * 
   * @param string $name
   * @param array $item_names
   */
  protected function _touchInfoItem($name,$item_names=array()){
    foreach ($item_names as $item_name){
      $this->_info[$name][$item_name] = time();
    }
  }

  /**
   * Gets read count of a cached value
   * 
   * @param string $name Cache name
   * 	 
   * @return int
   */
  public function getReadCount($name) {
    return $this->_getInfoItem($name, 'read_count');
  }

  /**
   * Gets write count of a cached value
   * 
   * @param string $name Cache name
   * 	 
   * @return int
   */
  public function getWriteCount($name) {
    return $this->_getInfoItem($name, 'write_count');
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
