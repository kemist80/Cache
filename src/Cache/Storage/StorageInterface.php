<?php

namespace Kemist\Cache\Storage;

/**
 * StorageInterface
 * 
 * @package Kemist\Cache
 * 
 * @version 1.0.4
 */
interface StorageInterface {

  public function has($name);

  public function delete($name = '');

  public function store($name, $val, $compressed = false);

  public function get($name, $compressed = false);

  public function info($getFields = false);

  public function init();

  public function getHits();

  public function getMisses();

  public function miss();

  public function hit();
}
