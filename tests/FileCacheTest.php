<?php

use Kemist\Cache\Storage\FileStorage;

class FileCacheTest extends \PHPUnit_Framework_TestCase {

  public function tearDown() {
    $cache = $this->getFileCache();
    $cache->clear();
    rmdir('temp');
  }

  protected function getFileCache() {
    if (!is_dir('temp')) {
      mkdir('temp');
    }
    $cache = new FileStorage(array('cache_dir' => 'temp'));
    return $cache;
  }

  public function testSuppressingCacheDir() {
    $cache = new FileStorage();
    $var = is_dir(sys_get_temp_dir() . '/kemist_cache/');
    $this->assertTrue($var);
    $cache->clear();
    rmdir(sys_get_temp_dir() . '/kemist_cache/');
  }

  public function testNotWritableCacheDir() {
    $cache = new FileStorage(array('cache_dir' => 'not_existing'));

    try {
      $ret = $cache->init();
    } catch (Exception $ex) {
      $ret = false;
    }

    $this->assertFalse($ret);
  }

  public function testNotExistingVariable() {
    $cache = $this->getFileCache();
    $var = $cache->exist('foo');
    $this->assertFalse($var);
  }

  public function testExistingVariable() {
    $cache = $this->getFileCache();
    $cache->put('test_variable1', 1);
    $var = $cache->exist('test_variable1');
    $this->assertTrue($var);
  }

  public function testGetNotExistingVariable() {
    $cache = $this->getFileCache();
    $var = $cache->get('foo');
    $this->assertFalse($var);
  }

  public function testReadBackCachedVariable() {
    $cache = $this->getFileCache();
    $test = 1;
    $cache->put('test_variable2', $test);
    $var = $cache->get('test_variable2');
    $this->assertEquals($var, $test);
  }

  public function testDeleteVariable() {
    $cache = $this->getFileCache();
    $cache->put('test_variable3', 1);
    $cache->clear('test_variable3');
    $this->assertFalse($cache->exist('test_variable'));
  }

  public function testDeleteNotExistingVariable() {
    $cache = $this->getFileCache();
    $cache->put('test_variable4', 1);
    $ret = $cache->clear('test_variable5');
    $this->assertFalse($ret);
  }

  public function testFlushCache() {
    $cache = $this->getFileCache();
    $cache->put('test_variable6', 1);
    $cache->put('test_variable7', 2);
    $cache->clear();
    $this->assertFalse($cache->exist('test_variable1'));
    $this->assertFalse($cache->exist('test_variable2'));
  }

  public function testReadBackCompressedVariable() {
    $cache = $this->getFileCache();
    $test = 'test text';
    $cache->put('test_variable8', $test, true);
    $var = $cache->get('test_variable8', true);
    $this->assertEquals($var, $test);
  }

  public function testReadBackCompressedFailure() {
    $cache = $this->getFileCache();
    $test = 'test text';
    $cache->put('test_variable9', $test, true);
    $var = $cache->get('test_variable9');
    $this->assertNotEquals($var, $test);
  }

  public function testInit() {
    $cache = $this->getFileCache();
    $init = $cache->init();
    $this->assertTrue($init);
  }

  public function testInfo() {
    $cache = $this->getFileCache();
    $cache->init();
    $cache->put('test_variable10',1);
    $var = $cache->info(true);
    $this->assertArrayHasKey('CACHE_HITS', $var);
  }

}
