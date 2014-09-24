<?php

use Kemist\Cache\Storage\Apc;

class ApcCacheTest extends \PHPUnit_Framework_TestCase {

  protected function _getApc() {
    $apc = $this->getMockBuilder('Kemist\Cache\Storage\ApcObject')->getMock();
    $apc->expects($this->any())
            ->method('put')
            ->will($this->returnValue(true))
    ;
    return $apc;
  }

  public function testInit() {
    $apc = $this->_getApc();
    $cache = new Apc($apc);
    $init = $cache->init();
    $this->assertTrue($init);
  }

  public function testNotExistingVariable() {
    $apc = $this->_getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new Apc($apc);
    $var = $cache->exist('foo');
    $this->assertFalse($var);
  }

  public function testExistingVariable() {
    $apc = $this->_getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue(true))
    ;
    $cache = new Apc($apc);
    $cache->put('test_variable', 1);
    $var = $cache->exist('test_variable');
    $this->assertTrue($var);
  }

  public function testReadBackCachedVariable() {
    $test = 1;
    $apc = $this->_getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue($test))
    ;
    $cache = new Apc($apc);
    $cache->put('test_variable', $test);
    $var = $cache->get('test_variable');
    $this->assertEquals($var, $test);
  }

  public function testDeleteVariable() {
    $apc = $this->_getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new Apc($apc);
    $cache->put('test_variable', 1);
    $cache->clear('test_variable');
    $this->assertFalse($cache->exist('test_variable'));
  }

  public function testFlushCache() {
    $apc = $this->_getApc();
    $apc->expects($this->any())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new Apc($apc);
    $cache->put('test_variable1', 1);
    $cache->put('test_variable2', 2);
    $cache->clear();
    $this->assertFalse($cache->exist('test_variable1'));
    $this->assertFalse($cache->exist('test_variable2'));
  }

  public function testReadBackCompressedVariable() {
    $test = 'test text';
    $apc = $this->_getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue($test))
    ;
    $cache = new Apc($apc);

    $cache->put('test_variable', $test, true);
    $var = $cache->get('test_variable', true);
    $this->assertEquals($var, $test);
  }

  public function testReadBackCompressedFailure() {
    $test = 'test text';
    $apc = $this->_getApc();
    $apc->expects($this->once())
            ->method('get')
            ->will($this->returnValue(false))
    ;
    $cache = new Apc($apc);
    $cache->put('test_variable', $test, true);
    $var = $cache->get('test_variable');
    $this->assertNotEquals($var, $test);
  }

  public function testInfo() {
    $apc = $this->_getApc();
    $apc->expects($this->once())
            ->method('info')
            ->will($this->returnValue(array()))
    ;
    $cache = new Apc($apc);
    $var = $cache->info(true);
    $this->assertArrayHasKey('CACHE_HITS', $var);
  }

}
