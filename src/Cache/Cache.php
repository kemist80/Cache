<?php

namespace Kemist\Cache;

use Kemist\Cache\Storage\StorageInterface;

/**
 * Cache object for caching variables
 * 
 * @package Kemist\Cache
 * 
 * @version 1.1.9
 */
class Cache {

  /**
   * Cache storage object
   * @var StorageInterface
   */
  protected $storage;

  /**
   * Caching is enabled
   * @var bool 
   */
  protected $enabled = true;

  /**
   * Key name encryption
   * @var bool 
   */
  protected $encrypt_keys = true;

  /**
   * Cache values information
   * @var Info
   */
  protected $info;

  /**
   * Read key names
   * @var array 
   */
  protected $read_keys = array();

  /**
   * System reserved info key
   * @var string 
   */
  protected $info_key = '_system.info';

  /**
   * Initialised (-1: not yet, 0: in progress, 1: initialised)
   * @var int 
   */
  protected $initialised = -1;

  const STORE_METHOD_SERIALIZE = 1;
  const STORE_METHOD_JSON = 2;

  /**
   * Constructor 
   * 
   * @param StorageInterface $storage
   * @param array $options
   */
  public function __construct(StorageInterface $storage, array $options = array()) {
    $this->storage = $storage;
    $this->enabled = (isset($options['enabled']) ? $options['enabled'] : true);
    $this->encrypt_keys = (isset($options['encrypt_keys']) ? $options['encrypt_keys'] : true);
    $this->info = new Info();
  }

  /**
   * Initialise (lazy)
   */
  public function init() {
    if ($this->initialised > -1) {
      return true;
    }

    // Initialising in progress 
    $this->initialised = 0;
    $this->storage->init();

    if ($this->exist($this->info_key)) {
      $info = (array) $this->getOrPut($this->info_key, array());
      array_walk($info, array($this, 'handleExpiration'));
      $this->info->setData($info);
    }
    $this->initialised = 1;
    return true;
  }

  /**
   * Handles cached value expiration
   * 
   * @param array $data
   * @param string $key
   * 
   * @return boolean
   */
  protected function handleExpiration($data, $key) {
    if (!isset($data['expiry']) || $data['expiry'] == 0) {
      return true;
    } elseif (!$this->exist($key)) {
      unset($this->info[$key]);
    } elseif (time() > $data['expiry']) {
      $this->clear($key);
    }
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
    return $this->info->getData($name);
  }

  /**
   * Check if Cache is enabled
   * 
   * @return bool
   */
  public function isEnabled() {
    return $this->enabled;
  }

  /**
   * Enable/disable caching
   * 
   * @param bool $enabled
   */
  public function setEnabled($enabled) {
    $this->enabled = (bool) $enabled;
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
    $secret = $this->encryptKey($name);
    return ($this->storage->exist($secret) && ($name == $this->info_key || isset($this->info[$name])));
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
    $secret = ($name != '' ? $this->encryptKey($name) : $name);
    $ret = $this->storage->clear($secret);

    if ($name == '') {
      $this->info = new Info();
    } elseif (isset($this->info[$name])) {
      unset($this->info[$name]);
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
    $secret = $this->encryptKey($name);
    $data = $this->encode($val, $store_method);
    if (false !== $ret = $this->storage->put($secret, $data, $compressed)) {
      $expiry = ($expiry == 'never' ? 0 : $this->extractExpiryDate($expiry));

      if (!isset($this->info[$name])) {
        $this->info->createData($name);
      }

      $this->info->touchItem($name, array('last_access', 'last_write'));
      $this->info->appendData($name, array(
          'expiry' => $expiry,
          'size' => strlen($data),
          'compressed' => $compressed,
          'store_method' => $store_method,
      ));
      $this->info->increaseItem($name, 'write_count');
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
  protected function extractExpiryDate($expiry) {
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
    if (!$this->isEnabled() || ($this->init() && $name != $this->info_key && !isset($this->info[$name]))) {
      $this->storage->miss();
      return $this->processDefault($default);
    }

    list($compressed, $store_method) = $this->extractParameters($name);
    $secret = $this->encryptKey($name);
    $raw = $this->storage->get($secret, $compressed);
    $ret = $this->decode($raw, $store_method);

    if ($ret !== null) {
      $this->info->touchItem($name, array('last_access', 'last_read'));
      $this->info->increaseItem($name, 'read_count');
      $this->read_keys[] = $name;
      array_unique($this->read_keys);
    } else {
      $this->info->deleteData($name);
    }

    return $ret;
  }

  /**
   * Extract cached value parameters
   * 
   * @param string $name
   * 
   * @return array
   */
  protected function extractParameters($name) {
    $compressed = ($name == $this->info_key ? true : $this->info->getItem($name, 'compressed'));
    $store_method = ($name == $this->info_key ? self::STORE_METHOD_JSON : $this->info->getItem($name, 'store_method'));
    return array($compressed, $store_method);
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
    $value = $this->processDefault($default);
    $this->put($name, $value, $compressed, $expiry, $store_method);
    return $value;
  }

  /**
   * Retrieves and deletes value from cache
   * 
   * @param string $name
   * 
   * @return mixed
   */
  public function pull($name) {
    $ret = $this->get($name);
    $this->delete($name);
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
   * @return array|bool
   */
  public function info($get_fields = false) {
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    return $this->storage->info($get_fields);
  }

  /**
   * Encodes variable with the specified method
   * 
   * @param mixed $var Variable
   * @param int $store_method serialize|json	 	 	 
   * 	 
   * @return mixed
   */
  protected function encode($var, $store_method = self::STORE_METHOD_SERIALIZE) {
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
  protected function decode($var, $store_method = self::STORE_METHOD_SERIALIZE) {
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
  protected function encryptKey($key) {
    return ($this->encrypt_keys ? sha1($key) : $key);
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
    return $this->storage->getHits();
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
    return $this->storage->getMisses();
  }

  /**
   * Stores cache values expiral information into cache
   */
  public function writeExpirals() {
    if (!$this->isEnabled() || $this->initialised < 1) {
      return false;
    }
    return $this->put($this->info_key, $this->info->getData(), true, 0, self::STORE_METHOD_JSON);
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
    return $this->info->getExpiry($name, $format);
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
    if ($this->canModify($name)) {
      $created = (int) $this->getCreated($name);
      $ttl = (int) $ttl;
      $this->info->setItem($name, 'expiry', ($ttl <= 0 ? 0 : $created + $ttl));
    }
  }

  /**
   * Modifies expiry
   * 
   * @param string $name
   * @param mixed $expiry
   */
  public function setExpiry($name, $expiry) {
    if ($this->canModify($name)) {
      $this->info->setItem($name, 'expiry', $this->extractExpiryDate($expiry));
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
    return $this->info->getItem($name, 'created', 'date', $format);
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
    return $this->info->getItem($name, 'last_access', 'date', $format);
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
    return $this->info->getItem($name, 'last_read', 'date', $format);
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
    return $this->info->getItem($name, 'last_write', 'date', $format);
  }

  /**
   * Gets read count of a cached value
   * 
   * @param string $name Cache name
   * 	 
   * @return int
   */
  public function getReadCount($name) {
    return $this->info->getItem($name, 'read_count', 'int');
  }

  /**
   * Gets write count of a cached value
   * 
   * @param string $name Cache name
   * 	 
   * @return int
   */
  public function getWriteCount($name) {
    return $this->info->getItem($name, 'write_count', 'int');
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
    return $this->info->getKeys();
  }

  /**
   * Gets cache key names which already read
   *  	 
   * @return array
   */
  public function getReadKeys() {
    return $this->read_keys;
  }

  /**
   * Gets storage object
   * 
   * @return StorageInterface
   */
  public function getStorage() {
    return $this->storage;
  }

  /**
   * Retrieves key encryption
   * 
   * @return bool
   */
  public function getEncryptKeys() {
    return $this->encrypt_keys;
  }

  /**
   * Sets key encryption
   * 
   * @param bool $encrypt_keys
   */
  public function setEncryptKeys($encrypt_keys) {
    $this->encrypt_keys = (bool) $encrypt_keys;
  }

  /**
   * Sets cache storage
   * 
   * @param StorageInterface $storage
   */
  public function setStorage(StorageInterface $storage) {
    $this->storage = $storage;
  }

  /**
   * Destructor
   */
  public function __destruct() {
    $this->writeExpirals();
  }

  /**
   * Sets a tagged cache value
   * 	 
   * @param string $name cache name
   * @param mixed $val variable to be stored
   * @param array $tags tags
   * @param bool $compressed Compressed storage
   * @param int|string $expiry Expires in the given seconds	(0:never) or the time defined by valid date string (eg. '2014-10-01' or '1week' or '2hours')
   * @param int $store_method Storing method (serialize|json)	 	 
   *
   * @return bool
   */
  public function putTagged($name, $val, $tags, $compressed = false, $expiry = 0, $store_method = self::STORE_METHOD_SERIALIZE) {
    if ($this->put($name, $val, $compressed, $expiry, $store_method)) {
      $this->prepareTags($tags);
      $this->info->setItem($name, 'tags', $tags);
      return true;
    }
  }

  /**
   * Alias of putTagged
   * 	 
   * @param string $name cache name
   * @param mixed $val variable to be stored
   * @param array $tags tags
   * @param bool $compressed Compressed storage
   * @param int|string $expiry Expires in the given seconds	(0:never) or the time defined by valid date string (eg. '2014-10-01' or '1week' or '2hours')
   * @param int $store_method Storing method (serialize|json)	 	 
   *
   * @return bool
   */
  public function setTagged($name, $val, $tags, $compressed = false, $expiry = 0, $store_method = self::STORE_METHOD_SERIALIZE) {
    return $this->putTagged($name, $val, $tags, $compressed, $expiry, $store_method);
  }

  /**
   * Gets tagged cache values
   * 
   * @param array $tags
   * 
   * @return array
   */
  public function getTagged($tags) {
    if (!$this->isEnabled()) {
      return false;
    }

    $this->init();
    $this->prepareTags($tags);
    $filtered = (array) $this->info->filterByTags($tags);
    $ret = array();
    foreach ($filtered as $key) {
      $ret[$key] = $this->get($key);
    }
    return $ret;
  }

  /**
   * Gets tags of a cached variable
   * 
   * @param string $key
   * 
   * @return array
   */
  public function getTags($key) {
    if (!$this->isEnabled()) {
      return false;
    }

    $this->init();
    $ret = $this->info->getItem($key, 'tags', 'array');
    sort($ret);
    return $ret;
  }

  /**
   * Sets tags of a cached variable
   * 
   * @param string $name
   * @param array $tags
   * 
   * @return array
   */
  public function setTags($name, $tags) {
    if ($this->canModify($name)) {
      $this->prepareTags($tags);
      return $this->info->setItem($name, 'tags', $tags);
    }
    return false;
  }

  /**
   * Adds tags for a cached variable
   * 
   * @param string $name
   * @param array $tags
   * 
   * @return array
   */
  public function addTags($name, $tags) {
    if ($this->canModify($name)) {
      $this->prepareTags($tags);
      $tags = array_unique(array_merge($this->getTags($name), $tags));
      return $this->setTags($name, $tags);
    }
    return false;
  }

  /**
   * Clears cache values matching the given tags
   * 
   * @param array $tags
   * 
   * @return array
   */
  public function clearTagged($tags) {
    if (!$this->isEnabled()) {
      return false;
    }

    $this->init();
    $this->prepareTags($tags);
    $filtered = (array) $this->info->filterByTags($tags);
    return array_map(array($this, 'clear'), $filtered);
  }

  /**
   * Alias of clearTagged
   * 
   * @param array $tags
   * 
   * @return array
   */
  public function deleteTagged($tags) {
    return $this->clearTagged($tags);
  }

  /**
   * Gets all tags currently in use
   * 
   * @return array
   */
  public function getAllTags() {
    if (!$this->isEnabled()) {
      return false;
    }

    $this->init();
    $tags = array();
    foreach ($this->info as $info) {
      $tags = array_unique(array_merge($tags, $info['tags']));
    }
    sort($tags);
    return $tags;
  }

  /**
   * Prepares tags parameter
   * 
   * @param array|string $tags
   */
  protected function prepareTags(&$tags) {
    if (!is_array($tags)) {
      $tags = array($tags);
    }
    $tags = array_unique($tags);
  }

  /**
   * Checks if cache value info can be modified (cache is enabled and value exists)
   * 
   * @param string $name
   * 
   * @return boolean
   */
  protected function canModify($name) {
    if (!$this->isEnabled()) {
      return false;
    }
    $this->init();
    return $this->exist($name);
  }

  /**
   * Processes default value
   * 
   * @param \Closure|mixed $default
   * 
   * @return mixed
   */
  protected function processDefault($default) {
    return ($default instanceof \Closure ? call_user_func($default) : $default);
  }

}