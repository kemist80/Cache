<?php

use Kemist\Cache\Storage\FileStorage;

class FileCacheTest extends \PHPUnit_Framework_TestCase {

  public function tearDown() {
    $cache = $this->getFileCache();
    $cache->delete();
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
    $cache->delete();
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
    $var = $cache->has('foo');
    $this->assertFalse($var);
  }

  public function testExistingVariable() {
    $cache = $this->getFileCache();
    $cache->store('test_variable1', 1);
    $var = $cache->has('test_variable1');
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
    $cache->store('test_variable2', $test);
    $var = $cache->get('test_variable2');
    $this->assertEquals($var, $test);
  }

  public function testDeleteVariable() {
    $cache = $this->getFileCache();
    $cache->store('test_variable3', 1);
    $cache->delete('test_variable3');
    $this->assertFalse($cache->has('test_variable'));
  }

  public function testDeleteNotExistingVariable() {
    $cache = $this->getFileCache();
    $cache->store('test_variable4', 1);
    $ret = $cache->delete('test_variable5');
    $this->assertFalse($ret);
  }

  public function testFlushCache() {
    $cache = $this->getFileCache();
    $cache->store('test_variable6', 1);
    $cache->store('test_variable7', 2);
    $cache->delete();
    $this->assertFalse($cache->has('test_variable1'));
    $this->assertFalse($cache->has('test_variable2'));
  }

  public function testReadBackCompressedVariable() {
    $cache = $this->getFileCache();
    $test = 'test text';
    $cache->store('test_variable8', $test, true);
    $var = $cache->get('test_variable8', true);
    $this->assertEquals($var, $test);
  }

  public function testReadBackCompressedFailure() {
    $cache = $this->getFileCache();
    $test = 'test text';
    $cache->store('test_variable9', $test, true);
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
    $cache->store('test_variable10',1);
    $var = $cache->info(true);
    $this->assertArrayHasKey('CACHE_HITS', $var);
  }

}
