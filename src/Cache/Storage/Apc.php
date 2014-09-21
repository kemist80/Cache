<?php 

namespace Kemist\Cache\Storage;

/**
 * Apc Storage
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.0
 */  
 
class Apc implements StorageInterface{
  
  /**
	 * Number of hits
	 * @var int
	 */	  
  protected $_hits=0;    

  /**
	 * Cached field names
	 * @var array
	 */	  
  protected $_fields=array();  

  /**
   * Key prefix to avoid collisions
   * @var string 
   */
  protected $_prefix='';


	/**
	 * Constructor
	 *
	 */
	public function __construct(array $options=array()){
    $this->_prefix=(isset($options['prefix']) ? $options['prefix'] : '');
	}
	
  
  
  /**
   * Initialise Cache storage
   * 
   * @return boolean
   * 
   * @throws \Kemist\Cache\Exception
   */
  public function init(){
    if (!extension_loaded('apc')) {
      throw new \Kemist\Cache\Exception("APC extension not loaded!");
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
	public function exist($name,$max_age=0){		
		if (apc_fetch($this->_prefix.$name)){
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
	public function clear($name=''){
		if ($name==''){		
			return apc_clear_cache('user');
		}else{
			return apc_delete($this->_prefix.$name);
		}
	}



	/**
	 * Saves the variable to the $name cache
	 *	 
	 * @param string $name cache name
	 * @param mixed $val variable to be stored
	 *
	 * @return bool
	 */
	public function put($name,$val, $encrypt=false){
		$ret=apc_store($this->_prefix.$name,$val);
    if ($ret && !in_array($name,$this->_fields)){
      $this->_fields[]=$name;
    }
    return $ret;
	}



	/**
	 * Retrieves the content of $name cache
	 *	 
	 * @param string $name cache name
	 *
	 * @return mixed
	 */
	public function get($name, $encrypt=false){
		$ret=apc_fetch($this->_prefix.$name);
		
		if ($ret!==false){
			$this->_hits++;
			if (!in_array($name,$this->_fields)){
				$this->_fields[]=$name;
			}
		}
		
		return $ret;
	}
	
	
	
	/**
	 * Retrieves information of Cache state
	 *	 
	 * @return array
	 */
	public function info($get_fields=false){
		$ret=array();
		$ret['CACHE_TYPE']='APC';
		$ret['CACHE_HITS']=$this->_hits;
		$ret=array_merge($ret,apc_cache_info('user'));

		if ($get_fields){
			foreach ($this->_fields as $field){
				$ret['field_content'][$field]=$this->get($field);
			}
		}
		
		return $ret;
	}

}




?>
