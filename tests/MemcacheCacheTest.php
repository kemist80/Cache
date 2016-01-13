<?php

use Kemist\Cache\Storage\MemcacheStorage;

class MemcacheCacheTest extends \PHPUnit_Framework_TestCase {

  protected function getMemCache() {
    $memcache = $this->getMock('\Memcache',array('connect','get','flush','delete','replace','set','getStats','close'));
    $memcache->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(true))
    ;
    return $memcache;
  }

  public function testInit() {
    $memcache = $this->getMemCache();
    $cache = new MemcacheStorage($memcache, array('server' => '127.0.0.1'));
    $init = $cache->init();
    $this->assertTrue($init);
  }

  public function testNotExistingVariable() {
    $memcache = $this->getMemCache();
    $memcache->expects($this->once())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new MemcacheStorage($memcache, array('server' => '127.0.0.1'));
    $var = $cache->exist('foo');
    $this->assertFalse($var);
  }

  public function testExistingVariable() {
    $memcache = $this->getMemCache();
    $memcache->expects($this->once())
            ->method('get')
            ->will($this->returnValue(true))
    ;
    $cache = new MemcacheStorage($memcache, array('server' => '127.0.0.1'));
    $cache->put('test_variable', 1);
    $var = $cache->exist('test_variable');
    $this->assertTrue($var);
  }

  public function testReadBackCachedVariable() {
    $test = 1;
    $memcache = $this->getMemCache();
    $memcache->expects($this->once())
            ->method('get')
            ->will($this->returnValue($test))
    ;
    $cache = new MemcacheStorage($memcache, array('server' => '127.0.0.1'));
    $cache->put('test_variable', $test);
    $var = $cache->get('test_variable');
    $this->assertEquals($var, $test);
  }

  public function testDeleteVariable() {
    $memcache = $this->getMemCache();
    $memcache->expects($this->once())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new MemcacheStorage($memcache, array('server' => '127.0.0.1'));
    $cache->put('test_variable', 1);
    $cache->clear('test_variable');
    $this->assertFalse($cache->exist('test_variable'));
  }

  public function testFlushCache() {
    $memcache = $this->getMemCache();
    $memcache->expects($this->any())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new MemcacheStorage($memcache, array('server' => '127.0.0.1'));
    $cache->put('test_variable1', 1);
    $cache->put('test_variable2', 2);
    $cache->clear();
    $this->assertFalse($cache->exist('test_variable1'));
    $this->assertFalse($cache->exist('test_variable2'));
  }

  public function testReadBackCompressedVariable() {
    $test = 'test text';
    $memcache = $this->getMemCache();
    $memcache->expects($this->once())
            ->method('get')
            ->will($this->returnValue($test))
    ;
    $cache = new MemcacheStorage($memcache, array('server' => '127.0.0.1'));

    $cache->put('test_variable', $test, true);
    $var = $cache->get('test_variable', true);
    $this->assertEquals($var, $test);
  }

  public function testReadBackCompressedFailure() {
    $test = 'test text';
    $memcache = $this->getMemCache();
    $memcache->expects($this->once())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new MemcacheStorage($memcache, array('server' => '127.0.0.1'));
    $cache->put('test_variable', $test, true);
    $var = $cache->get('test_variable');
    $this->assertNotEquals($var, $test);
  }

  public function testInfo() {
    $memcache = $this->getMemCache();
    $memcache->expects($this->once())
            ->method('getStats')
            ->will($this->returnValue(array()))
    ;
    $cache = new MemcacheStorage($memcache, array('server' => '127.0.0.1'));
    $var = $cache->info(true);
    $this->assertArrayHasKey('CACHE_HITS', $var);
  }

}
