<?php

use Kemist\Cache\Manager;

class CacheManagerTest extends \PHPUnit_Framework_TestCase {

  protected function _getStorage() {
    $storage = $this->getMock('Kemist\Cache\Storage\StorageInterface');
    $storage->expects($this->any())
            ->method('init')
            ->will($this->returnValue(true))
    ;
    return $storage;
  }

  public function testInit() {
    $storage = $this->_getStorage();
    $storage->expects($this->once())
            ->method('exist')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(json_encode(array('test' => array()))))
    ;
    $cache = new Manager($storage);
    $ret = $cache->init();
    $this->assertTrue($ret);
  }

  public function testExpired() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('exist')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(json_encode(array('test' => array('expiry' => time() - 10)))))
    ;
    $cache = new Manager($storage);
    $ret = $cache->init();
    $this->assertTrue($ret);
  }

  public function testSuppressingStoreMethod() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('exist')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(json_encode(array('test' => array('expiry' => time() + 10, 'store_method' => '')))))
    ;
    $cache = new Manager($storage);
    $ret = $cache->init();
    $this->assertTrue($ret);
  }

  public function testFalseValue() {
    $storage = $this->_getStorage();
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(serialize(false)))
    ;
    $cache = new Manager($storage);
    $cache->put('false_test',false);    
    $var = $cache->get('false_test');
    $this->assertFalse($var);
  }

  public function testEnabling() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->setEnabled(true);
    $this->assertTrue($cache->isEnabled());
  }

  public function testDisabling() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->setEnabled(false);
    $this->assertFalse($cache->isEnabled());
  }

  public function testCacheSerialize() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    $info = $cache->getInfo();
    $this->assertEquals($info['test_variable']['store_method'], Manager::STORE_METHOD_SERIALIZE);
  }

  public function testCacheJson() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->put('test_variable', 1, false, 0, Manager::STORE_METHOD_JSON);
    $info = $cache->getInfo();
    $this->assertEquals($info['test_variable']['store_method'], Manager::STORE_METHOD_JSON);
  }

  public function testSerializedVariable() {
    $storage = $this->_getStorage();
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    $ret = $cache->get('test_variable');
    $this->assertEquals($ret, 1);
  }

  public function testExpiry() {
    $storage = $this->_getStorage();

    $cache = new Manager($storage);
    $exp = time() + 86400;
    $cache->put('test_variable', 1, false, 86400);
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, $exp);
  }
  
  public function testNeverExpires() {
    $storage = $this->_getStorage();

    $cache = new Manager($storage);
    $cache->put('test_variable', 1, false, 'never');
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, 0);
  }
  
  public function testExpiryFullTimestamp() {
    $storage = $this->_getStorage();

    $cache = new Manager($storage);
    $timestamp=time()+86400;
    $cache->put('test_variable', 1, false, $timestamp);
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, $timestamp);
  }
  
  public function testExpiryRelativeOneHour() {
    $storage = $this->_getStorage();

    $cache = new Manager($storage);
    $timestamp=time()+3600;
    $cache->put('test_variable', 1, false, '1hour');
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, $timestamp);
  }
  
  public function testExpiryRelativeTwoDays() {
    $storage = $this->_getStorage();

    $cache = new Manager($storage);
    $timestamp=time()+(86400*2);
    $cache->put('test_variable', 1, false, '2days');
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, $timestamp);
  }
  
  public function testExpiryInvalidDateString() {
    $storage = $this->_getStorage();

    $cache = new Manager($storage);
    $ret=true;
    try{
      $cache->put('test_variable', 1, false, 'iNvAlId DaTeStRiNg');
    } catch (\InvalidArgumentException $ex) {
      $ret=false;
    }
    
    $this->assertFalse($ret);
  }
  
  public function testExpiryInPast() {
    $storage = $this->_getStorage();

    $cache = new Manager($storage);
    $cache->put('test_variable', 1, false, '1999-05-08');
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, 0);
  }

  public function testWriteCount() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    $cache->put('test_variable', 1);
    $cache->put('test_variable', 1);

    $write = $cache->getWriteCount('test_variable');
    $this->assertEquals($write, 3);
  }

  public function testReadCount() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    $cache->get('test_variable');
    $cache->get('test_variable');
    $cache->get('test_variable');
    $read = $cache->getReadCount('test_variable');
    $this->assertEquals($read, 3);
  }

  public function testCreated() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $time = time();
    $cache->put('test_variable', 1);
    sleep(1);
    $cache->put('test_variable', 1);
    
    $created = $cache->getCreated('test_variable');
    $this->assertEquals($created, $time);
  }

  public function testLastAccess() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    sleep(1);
    $time = time();
    $cache->get('test_variable');

    $last_access = $cache->getLastAccess('test_variable');
    $this->assertEquals($last_access, $time);
  }
  
  public function testLastRead() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    sleep(1);
    $time = time();
    $cache->get('test_variable');

    $last_access = $cache->getLastRead('test_variable');
    $this->assertEquals($last_access, $time);
  }
  
  public function testLastWrite() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    sleep(1);
    $time = time();
    $cache->put('test_variable',2);

    $last_access = $cache->getLastWrite('test_variable');
    $this->assertEquals($last_access, $time);
  }
  
  public function testGetHits() {
    $storage = $this->_getStorage();
    $storage->expects($this->once())
            ->method('getHits')
            ->will($this->returnValue(3))
    ;
    $cache = new Manager($storage);
    $this->assertEquals($cache->getHits(), 3);
  }

  public function testGetKeys() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable1', 1);
    $cache->put('test_variable2', 1);
    $cache->get('test_variable1');

    $keys = $cache->getKeys();
    $this->assertEquals($keys, array('test_variable1', 'test_variable2'));
  }

  public function testReadKeys() {
    $storage = $this->_getStorage();
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable1', 1);
    $cache->put('test_variable2', 2);
    $cache->get('test_variable1');

    $keys = $cache->getReadKeys();

    $this->assertEquals($keys, array('test_variable1'));
  }

  public function testSetEncryptKeysEnabled() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->setEncryptKeys(true);
    $this->assertTrue($cache->getEncryptKeys());
  }

  public function testSetEncryptKeysDisabled() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->setEncryptKeys(false);
    $this->assertFalse($cache->getEncryptKeys());
  }

  public function testDisabledExist() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('exist')
            ->will($this->returnValue(true))
    ;
    $cache = new Manager($storage);
    $cache->init();
    $cache->setEnabled(false);
    $this->assertFalse($cache->exist('_system.info'));
  }

  public function testDisabledGet() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(true))
    ;
    $cache = new Manager($storage);
    $cache->init();
    $cache->setEnabled(false);
    $this->assertNull($cache->get('_system.info'));
  }

  public function testDisabledPut() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('put')
            ->will($this->returnValue(true))
    ;
    $cache = new Manager($storage);
    $cache->init();
    $cache->setEnabled(false);
    $this->assertFalse($cache->put('test_variable', 1));
  }

  public function testDisabledClear() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('clear')
            ->will($this->returnValue(true))
    ;
    $cache = new Manager($storage);
    $cache->init();
    $cache->setEnabled(false);
    $this->assertFalse($cache->clear('test_variable'));
  }

  public function testDisabledGetHits() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->setEnabled(false);
    $this->assertEquals($cache->getHits(), 0);
  }

  public function testDisabledInfo() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->setEnabled(false);
    $this->assertFalse($cache->info());
  }

  public function testFlush() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->flush();
    $info = $cache->getInfo();
    $this->assertEquals($info, array());
  }

  public function testExpiryNotExisting() {
    $storage = $this->_getStorage();

    $cache = new Manager($storage);
    $cache->put('test_variable', 1, false, 86400);
    $expiry = $cache->getExpiry('test_variable2');
    $this->assertFalse($expiry);
  }

  public function testWriteCountNotExisting() {
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    $cache->put('test_variable', 1);
    $cache->put('test_variable', 1);

    $write = $cache->getWriteCount('test_variable2');
    $this->assertFalse($write);
  }

  public function testReadCountNotExisting() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    $cache->get('test_variable');
    $cache->get('test_variable');
    $cache->get('test_variable');
    $read = $cache->getReadCount('test_variable2');
    $this->assertFalse($read);
  }

  public function testCreatedNotExisting() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);

    $created = $cache->getCreated('test_variable2');
    $this->assertFalse($created);
  }

  public function testLastAccessNotExisting() {
    $storage = $this->_getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Manager($storage);
    $cache->put('test_variable', 1);
    sleep(1);
    $cache->get('test_variable');

    $last_access = $cache->getLastAccess('test_variable2');
    $this->assertFalse($last_access);
  }
  
  public function testDefaultValue(){
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $default=$cache->get('test_variable','default');
    $this->assertEquals($default,'default');
  }
  
  public function testDefaultClosure(){
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $default=$cache->get('test_variable',function(){ return 'default'; });
    $this->assertEquals($default,'default');
  }
  
  public function testGetOrPut(){
    $storage = $this->_getStorage();
    $cache = new Manager($storage);
    $default=$cache->getOrPut('test_variable','default');
    $this->assertEquals($default,'default');
    $info=$cache->getInfo();    
    $this->assertArrayHasKey('test_variable',$info);
  }

}
