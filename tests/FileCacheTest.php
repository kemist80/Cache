<?php

use Kemist\Cache\Storage\File;

class FileCacheTest extends \PHPUnit_Framework_TestCase {

  public function tearDown() {
    $cache = $this->_getFileCache();
    $cache->clear();
    rmdir('temp');
  }

  protected function _getFileCache() {
    if (!is_dir('temp')) {
      mkdir('temp');
    }
    $cache = new File(array('cache_dir' => 'temp'));
    return $cache;
  }

  public function testSuppressingCacheDir() {
    $cache = new File();
    $var = is_dir(sys_get_temp_dir() . '/kemist_cache/');
    $this->assertTrue($var);
    $cache->clear();
    rmdir(sys_get_temp_dir() . '/kemist_cache/');
  }

  public function testNotWritableCacheDir() {
    $cache = new File(array('cache_dir' => 'not_existing'));

    try {
      $ret = $cache->init();
    } catch (Exception $ex) {
      $ret = false;
    }

    $this->assertFalse($ret);
  }

  public function testNotExistingVariable() {
    $cache = $this->_getFileCache();
    $var = $cache->exist('foo');
    $this->assertFalse($var);
  }

  public function testExistingVariable() {
    $cache = $this->_getFileCache();
    $cache->put('test_variable1', 1);
    $var = $cache->exist('test_variable1');
    $this->assertTrue($var);
  }

  public function testGetNotExistingVariable() {
    $cache = $this->_getFileCache();
    $var = $cache->get('foo');
    $this->assertFalse($var);
  }

  public function testReadBackCachedVariable() {
    $cache = $this->_getFileCache();
    $test = 1;
    $cache->put('test_variable2', $test);
    $var = $cache->get('test_variable2');
    $this->assertEquals($var, $test);
  }

  public function testDeleteVariable() {
    $cache = $this->_getFileCache();
    $cache->put('test_variable3', 1);
    $cache->clear('test_variable3');
    $this->assertFalse($cache->exist('test_variable'));
  }

  public function testDeleteNotExistingVariable() {
    $cache = $this->_getFileCache();
    $cache->put('test_variable4', 1);
    $ret = $cache->clear('test_variable5');
    $this->assertFalse($ret);
  }

  public function testFlushCache() {
    $cache = $this->_getFileCache();
    $cache->put('test_variable6', 1);
    $cache->put('test_variable7', 2);
    $cache->clear();
    $this->assertFalse($cache->exist('test_variable1'));
    $this->assertFalse($cache->exist('test_variable2'));
  }

  public function testReadBackCompressedVariable() {
    $cache = $this->_getFileCache();
    $test = 'test text';
    $cache->put('test_variable8', $test, true);
    $var = $cache->get('test_variable8', true);
    $this->assertEquals($var, $test);
  }

  public function testReadBackCompressedFailure() {
    $cache = $this->_getFileCache();
    $test = 'test text';
    $cache->put('test_variable9', $test, true);
    $var = $cache->get('test_variable9');
    $this->assertNotEquals($var, $test);
  }

  public function testInit() {
    $cache = $this->_getFileCache();
    $init = $cache->init();
    $this->assertTrue($init);
  }

  public function testInfo() {
    $cache = $this->_getFileCache();
    $cache->init();
    $cache->put('test_variable10',1);
    $var = $cache->info(true);
    $this->assertArrayHasKey('CACHE_HITS', $var);
  }

}
