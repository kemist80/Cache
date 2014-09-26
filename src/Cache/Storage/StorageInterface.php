<?php

namespace Kemist\Cache\Storage;

/**
 * StorageInterface
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.2
 */
interface StorageInterface {

  public function exist($name);

  public function clear($name = '');

  public function put($name, $val, $compressed = false);

  public function get($name, $compressed = false);

  public function info($get_fields = false);

  public function init();
  
  public function getHits();
  
}
