<?php

use Kemist\Cache\Cache;

class CacheTest extends \PHPUnit_Framework_TestCase {

  protected function getStorage() {
    $storage = $this->getMock('Kemist\Cache\Storage\StorageInterface');
    $storage->expects($this->any())
            ->method('init')
            ->will($this->returnValue(true))
    ;
    return $storage;
  }

  public function testInit() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(json_encode(array('test' => array()))))
    ;
    $cache = new Cache($storage);
    $ret = $cache->init();
    $this->assertTrue($ret);
  }

  public function testExpired() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(json_encode(array('test' => array('expiry' => time() - 10)))))
    ;
    $cache = new Cache($storage);
    $ret = $cache->init();
    $this->assertTrue($ret);
  }

  public function testSuppressingStoreMethod() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(json_encode(array('test' => array('expiry' => time() + 10, 'store_method' => '')))))
    ;
    $cache = new Cache($storage);
    $ret = $cache->init();
    $this->assertTrue($ret);
  }

  public function testFalseValue() {
    $storage = $this->getStorage();
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(serialize(false)))
    ;
    $cache = new Cache($storage);
    $cache->store('false_test', false);
    $var = $cache->get('false_test');
    $this->assertFalse($var);
  }

  public function testEnabling() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->setEnabled(true);
    $this->assertTrue($cache->isEnabled());
  }

  public function testDisabling() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->setEnabled(false);
    $this->assertFalse($cache->isEnabled());
  }

  public function testCacheSerialize() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    $info = $cache->getInfo();
    $this->assertEquals($info['test_variable']['store_method'], Cache::STORE_METHOD_SERIALIZE);
  }

  public function testCacheJson() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->store('test_variable', 1, false, 0, Cache::STORE_METHOD_JSON);
    $info = $cache->getInfo();
    $this->assertEquals($info['test_variable']['store_method'], Cache::STORE_METHOD_JSON);
  }

  public function testSerializedVariable() {
    $storage = $this->getStorage();
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    $ret = $cache->get('test_variable');
    $this->assertEquals($ret, 1);
  }

  public function testExpiry() {
    $storage = $this->getStorage();

    $cache = new Cache($storage);
    $exp = time() + 86400;
    $cache->store('test_variable', 1, false, 86400);
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, $exp);
  }

  public function testNeverExpires() {
    $storage = $this->getStorage();

    $cache = new Cache($storage);
    $cache->store('test_variable', 1, false, 'never');
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, 0);
  }

  public function testExpiryFullTimestamp() {
    $storage = $this->getStorage();

    $cache = new Cache($storage);
    $timestamp = time() + 86400;
    $cache->store('test_variable', 1, false, $timestamp);
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, $timestamp);
  }

  public function testExpiryRelativeOneHour() {
    $storage = $this->getStorage();

    $cache = new Cache($storage);
    $timestamp = time() + 3600;
    $cache->store('test_variable', 1, false, '1hour');
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, $timestamp);
  }

  public function testExpiryRelativeTwoDays() {
    $storage = $this->getStorage();

    $cache = new Cache($storage);
    $timestamp = time() + (86400 * 2);
    $cache->store('test_variable', 1, false, '2days');
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, $timestamp);
  }

  public function testExpiryInvalidDateString() {
    $storage = $this->getStorage();

    $cache = new Cache($storage);
    $ret = true;
    try {
      $cache->store('test_variable', 1, false, 'iNvAlId DaTeStRiNg');
    } catch (\InvalidArgumentException $ex) {
      $ret = false;
    }

    $this->assertFalse($ret);
  }

  public function testExpiryInPast() {
    $storage = $this->getStorage();

    $cache = new Cache($storage);
    $cache->store('test_variable', 1, false, '1999-05-08');
    $expiry = $cache->getExpiry('test_variable');
    $this->assertEquals($expiry, 0);
  }

  public function testWriteCount() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    $cache->store('test_variable', 1);
    $cache->store('test_variable', 1);

    $write = $cache->getWriteCount('test_variable');
    $this->assertEquals($write, 3);
  }

  public function testReadCount() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    $cache->get('test_variable');
    $cache->get('test_variable');
    $cache->get('test_variable');
    $read = $cache->getReadCount('test_variable');
    $this->assertEquals($read, 3);
  }

  public function testCreated() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $time = time();
    $cache->store('test_variable', 1);
    sleep(1);
    $cache->store('test_variable', 1);

    $created = $cache->getCreated('test_variable');
    $this->assertEquals($created, $time);
  }

  public function testLastAccess() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    sleep(1);
    $time = time();
    $cache->get('test_variable');

    $last_access = $cache->getLastAccess('test_variable');
    $this->assertEquals($last_access, $time);
  }

  public function testLastRead() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    sleep(1);
    $time = time();
    $cache->get('test_variable');

    $last_access = $cache->getLastRead('test_variable');
    $this->assertEquals($last_access, $time);
  }

  public function testLastWrite() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    sleep(1);
    $time = time();
    $cache->store('test_variable', 2);

    $last_access = $cache->getLastWrite('test_variable');
    $this->assertEquals($last_access, $time);
  }

  public function testInfo() {
    $storage = $this->getStorage();
    $storage->expects($this->once())
            ->method('info')
            ->will($this->returnValue(array()))
    ;
    $cache = new Cache($storage);
    $this->assertEquals($cache->info(), array());
  }

  public function testGetHits() {
    $storage = $this->getStorage();
    $storage->expects($this->once())
            ->method('getHits')
            ->will($this->returnValue(3))
    ;
    $cache = new Cache($storage);
    $this->assertEquals($cache->getHits(), 3);
  }

  public function testGetKeys() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable1', 1);
    $cache->store('test_variable2', 1);
    $cache->get('test_variable1');

    $keys = $cache->getKeys();
    $this->assertEquals($keys, array('test_variable1', 'test_variable2'));
  }

  public function testReadKeys() {
    $storage = $this->getStorage();
    $storage->expects($this->once())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable1', 1);
    $cache->store('test_variable2', 2);
    $cache->get('test_variable1');

    $keys = $cache->getReadKeys();

    $this->assertEquals($keys, array('test_variable1'));
  }

  public function testSetEncryptKeysEnabled() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->setEncryptKeys(true);
    $this->assertTrue($cache->getEncryptKeys());
  }

  public function testSetEncryptKeysDisabled() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->setEncryptKeys(false);
    $this->assertFalse($cache->getEncryptKeys());
  }

  public function testDisabledExist() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(array()))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->setEnabled(false);
    $this->assertFalse($cache->has('_system.info'));
  }

  public function testDisabledGet() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->setEnabled(false);
    $this->assertNull($cache->get('_system.info'));
  }

  public function testDisabledCreated() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->setEnabled(false);
    $this->assertFalse($cache->getCreated('_system.info'));
  }

  public function testDisabledPut() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('store')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->setEnabled(false);
    $this->assertFalse($cache->store('test_variable', 1));
  }

  public function testDisabledClear() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('delete')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->setEnabled(false);
    $this->assertFalse($cache->delete('test_variable'));
  }

  public function testDisabledGetHits() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->setEnabled(false);
    $this->assertEquals($cache->getHits(), 0);
  }
  
  public function testDisabledGetMisses() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->setEnabled(false);
    $this->assertEquals($cache->getMisses(), 0);
  }

  public function testDisabledInfo() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->setEnabled(false);
    $this->assertFalse($cache->info());
  }
  
  public function testDisabledGetInfo() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->setEnabled(false);
    $this->assertFalse($cache->getInfo());
  }
  
  public function testDisabledGetExpiry() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->store('test_variable', 1, false, 86400);
    $cache->setEnabled(false);
    $this->assertFalse($cache->getExpiry('test_variable'));
  }
  
  public function testSetExpiry() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1, false, 86400);
    $cache->setExpiry('test_variable',0);
    $this->assertEquals($cache->getExpiry('test_variable'),0);
  }

  public function testFlush() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->flush();
    $info = $cache->getInfo();
    $this->assertEquals($info, array());
  }

  public function testExpiryNotExisting() {
    $storage = $this->getStorage();

    $cache = new Cache($storage);
    $cache->store('test_variable', 1, false, 86400);
    $expiry = $cache->getExpiry('test_variable2');
    $this->assertNull($expiry);
  }

  public function testWriteCountNotExisting() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    $cache->store('test_variable', 1);
    $cache->store('test_variable', 1);

    $write = $cache->getWriteCount('test_variable2');
    $this->assertFalse($write);
  }

  public function testReadCountNotExisting() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    $cache->get('test_variable');
    $cache->get('test_variable');
    $cache->get('test_variable');
    $read = $cache->getReadCount('test_variable2');
    $this->assertFalse($read);
  }

  public function testCreatedNotExisting() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);

    $created = $cache->getCreated('test_variable2');
    $this->assertFalse($created);
  }

  public function testLastAccessNotExisting() {
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('get')
            ->will($this->returnValue(serialize(1)))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    sleep(1);
    $cache->get('test_variable');

    $last_access = $cache->getLastAccess('test_variable2');
    $this->assertFalse($last_access);
  }

  public function testDefaultValue() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $default = $cache->get('test_variable', 'default');
    $this->assertEquals($default, 'default');
  }

  public function testDefaultClosure() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $default = $cache->get('test_variable', function() {
      return 'default';
    });
    $this->assertEquals($default, 'default');
  }

  public function testGetOrStore() {
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $default = $cache->getOrStore('test_variable', 'default');
    $this->assertEquals($default, 'default');
    $info = $cache->getInfo();
    $this->assertArrayHasKey('test_variable', $info);
  }
  
  public function testStorage(){
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $storage2=$this->getStorage();
    $cache->setStorage($storage2);
    $this->assertEquals($storage2, $cache->getStorage());
  }
  
  public function testGetTTL(){
    $storage = $this->getStorage();
    $cache = new Cache($storage);
    $cache->store('test_variable', 1, false, 300);
    $this->assertEquals($cache->getTTL('test_variable'), 300);
  }
  
  public function testSetTTL(){
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->store('test_variable', 1);
    $cache->setTTL('test_variable', 20);
    $this->assertEquals($cache->getTTL('test_variable'), 20);
  }
  
  public function testMisses(){
    $storage = $this->getStorage();
    $storage->expects($this->once())
            ->method('getMisses')
            ->will($this->returnValue(3))
    ;
    $cache = new Cache($storage);
    $this->assertEquals($cache->getMisses(), 3);
  }
  
  public function testPutTagged(){
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('store')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $tags=array('test1','test2');
    $cache->storeTagged('tagged','value',$tags);
    $this->assertEquals($cache->getTags('tagged'), $tags);
  }
  
  public function testPutTaggedString(){
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('store')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->storeTagged('tagged','value','test1');
    $this->assertEquals($cache->getTags('tagged'), array('test1'));
  }
  
  public function testGetTagged(){
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('store')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $tags=array('test1','test2');
    $cache->storeTagged('tagged','value',$tags);
    $this->assertEquals(array_keys($cache->getTagged($tags)), array('tagged'));
  }
  
  public function testSetTags(){
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->any())
            ->method('store')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->storeTagged('tagged','value',array('test1','test2'));
    $new_tags=array('test3','test4');
    $cache->setTags('tagged',$new_tags);
    $this->assertEquals($cache->getTags('tagged'), $new_tags);
  }  
  
  public function testAddTags(){
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->any())
            ->method('store')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->storeTagged('tagged','value',array('test1','test2'));
    $cache->addTags('tagged',array('test3','test4'));
    $this->assertEquals($cache->getTags('tagged'), array('test1','test2','test3','test4'));
  }    
  
  public function testClearTagged(){
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->any())
            ->method('store')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->any())
            ->method('delete')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->storeTagged('tagged','value',array('test1','test2'));
    $cache->deleteTagged('test1');
    $this->assertEquals($cache->getTagged('test1'), array());
  }   

  public function testGetAllTags(){
    $storage = $this->getStorage();
    $storage->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true))
    ;
    $storage->expects($this->any())
            ->method('store')
            ->will($this->returnValue(true))
    ;
    $cache = new Cache($storage);
    $cache->init();
    $cache->storeTagged('tagged1','value1',array('test1','test2'));
    $cache->storeTagged('tagged2','value2',array('test1','test3','test4'));    
    $this->assertEquals($cache->getAllTags(), array('test1','test2','test3','test4'));
  }
}
