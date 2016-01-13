<?php

use Kemist\Cache\Storage\ApcStorage;

class ApcCacheTest extends \PHPUnit_Framework_TestCase {

  protected function getApc() {
    $apc = $this->getMock('Kemist\Cache\Storage\ApcObject');
    $apc->expects($this->any())
            ->method('put')
            ->will($this->returnValue(true))
    ;
    return $apc;
  }

  public function testInit() {
    $apc = $this->getApc();
    $cache = new ApcStorage($apc);
    $init = $cache->init();
    $this->assertTrue($init);
  }

  public function testNotExistingVariable() {
    $apc = $this->getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new ApcStorage($apc);
    $var = $cache->exist('foo');
    $this->assertFalse($var);
  }

  public function testExistingVariable() {
    $apc = $this->getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue(true))
    ;
    $cache = new ApcStorage($apc);
    $cache->put('test_variable', 1);
    $var = $cache->exist('test_variable');
    $this->assertTrue($var);
  }

  public function testReadBackCachedVariable() {
    $test = 1;
    $apc = $this->getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue($test))
    ;
    $cache = new ApcStorage($apc);
    $cache->put('test_variable', $test);
    $var = $cache->get('test_variable');
    $this->assertEquals($var, $test);
  }

  public function testDeleteVariable() {
    $apc = $this->getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new ApcStorage($apc);
    $cache->put('test_variable', 1);
    $cache->clear('test_variable');
    $this->assertFalse($cache->exist('test_variable'));
  }

  public function testFlushCache() {
    $apc = $this->getApc();
    $apc->expects($this->any())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new ApcStorage($apc);
    $cache->put('test_variable1', 1);
    $cache->put('test_variable2', 2);
    $cache->clear();
    $this->assertFalse($cache->exist('test_variable1'));
    $this->assertFalse($cache->exist('test_variable2'));
  }

  public function testReadBackCompressedVariable() {
    $test = 'test text';
    $apc = $this->getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue($test))
    ;
    $cache = new ApcStorage($apc);

    $cache->put('test_variable', $test, true);
    $var = $cache->get('test_variable', true);
    $this->assertEquals($var, $test);
  }

  public function testReadBackCompressedFailure() {
    $test = 'test text';
    $apc = $this->getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new ApcStorage($apc);
    $cache->put('test_variable', $test, true);
    $var = $cache->get('test_variable');
    $this->assertNotEquals($var, $test);
  }

  public function testInfo() {
    $apc = $this->getApc();
    $apc->expects($this->once())
            ->method('info')
            ->will($this->returnValue(array()))
    ;
    $cache = new ApcStorage($apc);
    $var = $cache->info(true);
    $this->assertArrayHasKey('CACHE_HITS', $var);
  }

}
