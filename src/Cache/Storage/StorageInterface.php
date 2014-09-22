<?php

namespace Kemist\Cache\Storage;

/**
 * StorageInterface
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.0
 */
interface StorageInterface {

  public function exist($name, $max_age = 0);

  public function clear($name = '');

  public function put($name, $val, $compressed = false);

  public function get($name, $compressed = false);

  public function info($get_fields = false);

  public function init();
}
