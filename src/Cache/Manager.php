<?php 

namespace Kemist\Cache;
use Kemist\Cache\Storage\StorageInterface;

/**
 * Cache manager object for caching variables
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.0
 */  
 
class Cache{  
  
  /**
	 * Cache storage object
	 *	 	
	 * @var string
	 * @access private
	 */	
  protected $_storage;
  
  /**
   * Caching is enabled
   * @var bool 
   */
  protected $_enabled=true;
  
  /**
   * Key name encryption
   * @var bool 
   */
  protected $_encrypt_keys=true;
  
  /**
	 * Cache values information
	 * @var array
	 */	    
  protected $_info=array();
  
  /**
   * Read key names
   * @var array 
   */
  protected $_read_keys=array();
  
  /**
   * Initialised (-1: not yet, 0: in progress, 1: initialised)
   * @var int 
   */
  protected $_initialised=-1;
  
  const STORE_METHOD_SERIALIZE=1;    
  const STORE_METHOD_JSON=2;    


	/**
	 * Constructor
	 *
	 */
	public function __construct(StorageInterface $storage, array $options=array()){   
		$this->_storage=$storage;		
    $this->_enabled=(isset($options['enabled']) ? $options['enabled'] : true);
    $this->_encrypt_keys=(isset($options['encrypt_keys']) ? $options['encrypt_keys'] : true);
	}
  
  
  
  /**
   * Initialise (lazy)
   */
  public function init(){    
    if ($this->_initialised > -1){
      return true;
    }
    
    // Initialising in progress 
    $this->_initialised=0;
    if (!$this->_storage->init() && $this->isEnabled()){
      return false;
    }
    		
		if ($this->exist('_system.info')){
			$info=$this->get('_system.info',true,self::STORE_METHOD_JSON);
      $this->_info=(is_array($info) ? $info : array());
			foreach ($this->_info as $key=>$data){
				if (!isset($data['expire']) || $data['expire']==0){
					continue;
				}
				if ((time()>$data['expire'] && $this->exist($key))
            ||
            strlen($data['store_method'])==0
          ){
					$this->clear($key);
				}
				if (!$this->exist($key)){
					unset($this->_info[$key]);
				}
			}
		}
    $this->_initialised=1;
    return true;
  }
  
  
  
  /**
   * Check if Cache is enabled
   * @return bool
   */
  public function isEnabled(){
    return $this->_enabled;
  }
  
  
  
  /**
   * Check if Cache is enabled
   * @return bool
   */
  public function setEnabled($enabled){
    $this->_enabled=(bool)$enabled;
  }
	
	
	
	/**
	 * Check if $name cache exists and not older than $max_age
	 *	 
	 * @param string $name cache name
	 * @param int $max_age cache max lifetime
	 *
	 * @return bool
	 */
	public function exist($name, $max_age=0){
    if (!$this->isEnabled()){
      return false;
    }
    
    $this->init();
    $secret=$this->_encryptKey($name);
		return ($this->_storage->exist($secret, $max_age) && ($name=='_system.info' || isset($this->_info[$name])));
	}



	/**
	 * Deletes the specified cache or each one if '' given
	 *	 
	 * @param string $name cache name
	 *
	 * @return bool
	 */
	public function clear($name=''){
    if (!$this->isEnabled()){
      return false;
    }
    
    $this->init();
    $secret=($name!='' ? $this->_encryptKey($name) : $name);
		$ret=$this->_storage->clear($secret);
		
		if (isset($this->_info[$name])){
			unset($this->_info[$name]);
		}
	
		if ($name==''){
			$this->_info=array();
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
	public function delete($name=''){ 
		return $this->clear($name);
	}

  
  
	/**
	 * Flush all from cache
	 *	 
	 * @return bool
	 */
	public function flush(){ 
		return $this->clear();
	}

  

	/**
	 * Saves the variable to the $name cache
	 *	 
	 * @param string $name cache name
	 * @param mixed $val variable to be stored
	 * @param bool $compressed Compressed storage
	 * @param int $expires Expires in the given seconds	(0:never) 
	 * @param string $store_method Storing method (serialize|json)	 	 
	 *
	 * @return bool
	 */
	public function put($name, $val, $compressed=false, $expires=0, $store_method=self::STORE_METHOD_SERIALIZE){
    if (!$this->isEnabled()){
      return false;
    }
    
    $this->init();
    $secret=$this->_encryptKey($name);
    $data=$this->_encode($val,$store_method);
		$ret=$this->_storage->put($secret, $data, $compressed);
		
    $read_count=(isset($this->_info[$name]['read_count']) ? $this->_info[$name]['read_count'] : 0);    
    $write_count=(isset($this->_info[$name]['write_count']) ? $this->_info[$name]['write_count'] : 0);
    
		$this->_info[$name]=array(
			'expire'=>($expires==0 ? 0 : time()+$expires),
			'size'=>strlen($data),
			'compressed'=>$compressed,
			'store_method'=>$store_method,
      'created'=>time(),
			'last_access'=>time(),
      'read_count'=>$read_count,
      'write_count'=>++$write_count
		);
						
		return $ret;
	}



	/**
	 * Alias for storing a value in cache
	 *	 
	 * @param string $name cache name
	 * @param mixed $val variable to be stored
	 * @param bool $compressed Compressed storage
	 * @param int $expires Expires in the given seconds	(0:never) 
	 * @param int $store_method Storing method (serialize|json)	 	 
	 *
	 * @return bool
	 */
	public function set($name, $val, $compressed=false, $expires=0, $store_method=self::STORE_METHOD_SERIALIZE){
		return $this->put($name, $val, $compressed, $expires, $store_method);
	}
  
  
  
  /**
	 * Alias for storing a value in cache
	 *	 
	 * @param string $name cache name
	 * @param mixed $val variable to be stored
	 * @param bool $compressed Compressed storage
	 * @param int $expires Expires in the given seconds	(0:never) 
	 * @param int $store_method Storing method (serialize|json)	 	 
	 *
	 * @return bool
	 */
	public function store($name, $val, $compressed=false, $expires=0, $store_method=self::STORE_METHOD_SERIALIZE){
		return $this->put($name, $val, $compressed, $expires, $store_method);
	}



	/**
	 * Retrieves the content of $name cache
	 *	 
	 * @param string $name cache name
	 * @param bool $compressed Compressed storage
	 * @param int $store_method Storing method (serialize|json)	
	 * 	 
	 * @return mixed
	 */
	public function get($name, $compressed=false, $store_method=self::STORE_METHOD_SERIALIZE){    
    if (!$this->isEnabled() || ($this->init() && $name!='_system.info' && !isset($this->_info[$name]))){
      return false;
    }
    
    $secret=$this->_encryptKey($name);
    $raw=$this->_storage->get($secret, $compressed);
		$ret=$this->_decode($raw,$store_method);

		$this->_info[$name]['last_access']=time();
    
    $this->_info[$name]['read_count']=(isset($this->_info[$name]['read_count']) ? ++$this->_info[$name]['read_count'] : 1);
    
    if ($ret){
      $this->_read_keys[]=$name;
      array_unique($this->_read_keys);
    }elseif(isset($this->_info[$name])){
      unset($this->_info[$name]);
    }    
				
		return $ret;
	}
	
  
  
	/**
	 * Retrieves the content of $name cache
	 *	 
	 * @param string $name cache name
	 * @param bool $compressed Compressed storage
	 * @param int $store_method Storing method (serialize|json)	
	 * 	 
	 * @return mixed
	 */
  public function retrieve($name, $compressed=false, $store_method=self::STORE_METHOD_SERIALIZE){
		return $this->get($name, $compressed, $store_method);
	}
  
  
  
  /**
	 * Retrieves the content of $name cache
	 *	 
	 * @param string $name cache name
	 * @param bool $compressed Compressed storage
	 * @param int $store_method Storing method (serialize|json)	
	 * 	 
	 * @return mixed
	 */
  public function load($name, $compressed=false, $store_method=self::STORE_METHOD_SERIALIZE){
		return $this->get($name, $compressed, $store_method);
	}

	
	
	/**
	 * Retrieves information of Cache state
	 *	 
	 * @return array
	 */
	public function info($get_fields=false){
    $this->init();
		return $this->_storage->info($get_fields);
	}
	
	

	/**
	 * Encodes variable by the specified method
	 * 
	 * @param mixed $var Variable
	 * @param int $store_method serialize|json	 	 	 
	 *	 
	 * @return mixed
	 */	
	protected function _encode($var, $store_method=self::STORE_METHOD_SERIALIZE){
		switch ($store_method){
			case self::STORE_METHOD_JSON:
				$var=json_encode($var);
				break;
			case self::STORE_METHOD_SERIALIZE:
			default:
				$var=serialize($var);
		}
		return $var;
	}
	
	

	/**
	 * Decodes variable by the specified method
	 * 
	 * @param mixed $var Variable
	 * @param int $store_method serialize|json	 	 	 
	 *	 
	 * @return mixed
	 */		
	protected function _decode($var, $store_method=self::STORE_METHOD_SERIALIZE){
		if ($var){
			switch ($store_method){
				case self::STORE_METHOD_JSON:
					$var=json_decode($var,true);
					break;
				case self::STORE_METHOD_SERIALIZE:
				default:
					$var=unserialize($var);
			}
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
  protected function _encryptKey($key){
    return ($this->_encrypt_keys ? sha1($key) : $key);
  }
  
  

	/**
	 * Gets cache hits
	 * 
	 * @return int
	 */		
	public function getHits(){
    if (!$this->isEnabled()){
      return 0;
    }
    $this->init();
		return $this->_storage->hits;
	}



	/**
	 * Stores cache values expiral information into cache
	 */	
	public function writeExpirals(){
    if (!$this->isEnabled() || $this->_initialised < 1){
      return false;
    }
		$this->put('_system.info',$this->_info,true,0,self::STORE_METHOD_JSON);
	}
	
	

	/**
	 * Gets expiral information of a cached value (0: never)
	 * 
	 * @param string $key Cache name
	 * 	 
	 * @return int Timestamp when value expires	 	 
	 */		
	public function getExpire($key){
		if (!isset($this->_info[$key])){
			return false;
		}
    return isset($this->_info[$key]['expire']) ? $this->_info[$key]['expire'] : null;
	}
  
  
  
  /**
	 * Gets created time of a cached value
	 * 
	 * @param string $key Cache name
	 * 	 
	 * @return int Timestamp when value was created	 	 
	 */		
	public function getCreated($key){
		if (!isset($this->_info[$key])){
			return false;
		}
    return isset($this->_info[$key]['created']) ? $this->_info[$key]['created'] : null;
	}
  
  
  
  /**
	 * Gets last accessed time of a cached value
	 * 
	 * @param string $key Cache name
	 * 	 
	 * @return int Timestamp when value was last accessed	 	 
	 */		
	public function getLastAccess($key){
		if (!isset($this->_info[$key])){
			return false;
		}
    return isset($this->_info[$key]['last_access']) ? $this->_info[$key]['last_access'] : null;
	}
  
  
  
  /**
	 * Gets read count of a cached value
	 * 
	 * @param string $key Cache name
	 * 	 
	 * @return int
	 */		
	public function getReadCount($key){
		if (!isset($this->_info[$key])){
			return false;
		}
    return isset($this->_info[$key]['read_count']) ? $this->_info[$key]['read_count'] : 0;
	}  
  
  
  
  /**
	 * Gets write count of a cached value
	 * 
	 * @param string $key Cache name
	 * 	 
	 * @return int
	 */		
	public function getWriteCount($key){
		if (!isset($this->_info[$key])){
			return false;
		}
		return isset($this->_info[$key]['write_count']) ? $this->_info[$key]['write_count'] : 0;
	}  
  


	/**
	 * Gets all cache key names
	 *  	 
	 * @return array
	 */	
	public function getKeys(){
		return array_keys($this->_info);
	}
  
  
  
  /**
	 * Gets cache key names which already read
	 *  	 
	 * @return array
	 */	
	public function getReadKeys(){
		return array_keys($this->_read_keys);
	}
  
  
  
  /**
   * Gets storage object
   * 
   * @return StorageInterface
   */
  public function getStorage(){
    return $this->_storage;            
  }
  
  
  
  /**
   * Retrieves key encryption
   * 
   * @return bool
   */
  public function getEncryptKeys(){
    return $this->_encrypt_keys;
  }
  
  
  
  /**
   * Sets key encryption
   * 
   * @param bool $encrypt_keys
   */
  public function setEncryptKeys($encrypt_keys){
    $this->_encrypt_keys=(bool)$encrypt_keys;
  }
  
  
  
  /**
   * Sets cache storage
   * 
   * @param StorageInterface $storage
   */
  public function setStorage(StorageInterface $storage){
    $this->_storage=$storage;
  }
  
  
  
  /**
   * Destructor
   */
  public function __destruct(){
    $this->writeExpirals();
  }

}
