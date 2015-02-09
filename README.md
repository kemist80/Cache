# Cache

[![Build Status](https://travis-ci.org/kemist80/Cache.svg)](https://travis-ci.org/kemist80/Cache)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/kemist80/Cache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/kemist80/Cache/?branch=master)
[![Coverage Status](https://img.shields.io/coveralls/kemist80/Cache.svg)](https://coveralls.io/r/kemist80/Cache?branch=master)
[![Latest Stable Version](https://poser.pugx.org/kemist/cache/v/stable.svg)](https://packagist.org/packages/kemist/cache)
[![License](https://poser.pugx.org/kemist/cache/license.svg)](https://packagist.org/packages/kemist/cache)


Caching library with the following features:
- Extensible storage interface
- Automatic expiration handling even if cache storage doesn't support it
- Remembers when each cached item was read/written/accessed last time or written for the first time (created).
- Counts how many times a cached item was read/written since creation
- Counts cache hits (number of successful  retrieving)
- Counts cache misses (number of attempts to retrieve not existing or expired cache item)
- Supports compressed storing 
- Tagged caching


## Installation


Via composer:

```json
{
    "require": {
        "kemist/cache": "1.0.*"
    }
}
```

## Usage

Initialization:

```php
<?php

require_once('vendor/autoload.php');
use Kemist\Cache\Manager as Cache;

$storage=new Kemist\Cache\Storage\File();
$cache=new Cache($storage);

```

Basic usage:
```php
// Stores a variable in cache
$cache->put('variable','test variable');

// Reads back a variable from cache with existence checking
if ($cache->exist('variable')){
  echo $cache->get('variable');
}

// Deletes a variable from cache
$cache->delete('variable');

// Reads back a variable passing a default value in case it doesn't exist
echo $cache->get('variable','default value');

// Reads back a variable and deletes it from cache
$var=$cache->pull('variable');

// Deletes all cached variables
$cache->flush();

```
Compressed storing:
```php
// Stores a variable in cache compressed (in case current storage adapter supports it)
$cache->put('test.compressed','test variable',true);

// By reading back you don't need to know whether variable was compressed or not
echo $cache->get('test.compressed');

```
Specify cached variable expiry:
```php
// Stores a variable until manual deletion (default working)
$cache->put('test.manual','test variable',false,0);

// Stores a variable valid for 5 minutes (specified in seconds)
$cache->put('test.five_minutes','test variable',false,300);

// Stores a variable valid until specified date
$cache->put('test.concrete_date','test variable',false,'2015-01-01');

// Stores a variable valid for 2 weeks (you can use any valid date string)
$cache->put('test.two_weeks','test variable',false,'2weeks');

// Gets a cached item TTL (time to live: the difference between expiration and creation time in seconds)
echo $cache->getTTL('test.five_minutes');

// You can set TTL without modifying cached value
echo $cache->setTTL('test.manual',300);

// You can do the same with specifying the expiry date
echo $cache->setExpiry('test.manual','5 minutes');

```
Storing method:
```php
$object=new stdClass();
$object->property=5;
// By default every variable is stored serialized so even objects can be cached
$cache->put('test.object',$object,false,0,Cache::STORE_METHOD_SERIALIZE);
var_dump($cache->get('test.object'));

$array=array('a'=>'apple','b'=>'bike');
// You can switch storing method to JSON for arrays if you prefer
$cache->put('test.array',$array,false,0,Cache::STORE_METHOD_JSON);
// By reading back you don't need to know what was the storing method of the variable
var_dump($cache->get('test.array'));

```
Default value:
```php
// Get can return a default value if cached variable doesn't exist
echo $cache->get('test.default','default');

// You can pass even a closure
echo $cache->get('test.default',function(){return 'default';});

// Initial value setting (store default value if cached variable doesn't exist)
echo $cache->getOrPut('test.initial','initial',false,'3hours');

// The same with closure
echo $cache->getOrPut('test.initial',function(){return 'initial';},false,'3hours');

```
Tagged caching:
```php
// Sets cache value and assigns tags to it
$cache->putTagged('test.tagged1','tagged',array('tag1','tag2'));
$cache->putTagged('test.tagged2','tagged2','tag2');

// Retrieves cache values having the given tags
var_dump($cache->getTagged('tag2'));

// Adds more tags to the specified cache value
$cache->addTags('test.tagged2','tag3');

// Change tags without modifying cache value
$cache->setTags('test.tagged1',array('tag2','tag3','tag4'));

// Retrieves tags of a cached value
$cache->getTags('test.tagged1');

// Deletes cache variables having specified tags
var_dump($cache->deleteTagged('tag2'));

```
Cached variable statistics:
```php
// Displays cache creation time (first stored) in specified date format 
// (by default it returns a unix timestamp)
echo 'Created:'.$cache->getCreated('test.compressed','Y-m-d H:i:s');

// Displays cache expiry in specified date format
echo 'Expiry:'.$cache->getExpiry('test.two_weeks','Y-m-d H:i:s');

// Displays time when cached variable was last accessed (either read or write)
echo 'Last access:'.$cache->getLastAccess('test.array','Y-m-d H:i:s');

// Displays time when cached variable was last read
echo 'Last read:'.$cache->getLastRead('test.object','Y-m-d H:i:s');

// Displays time when cached variable was last written 
echo 'Last write:'.$cache->getLastWrite('test.array','Y-m-d H:i:s');

// Displays how many times cached value was written since creation
echo 'Write count:'.$cache->getWriteCount('variable');

// Displays how many times cached value was read since creation
echo 'Read count:'.$cache->getReadCount('test.compressed');

// Displays cache hits
echo 'Cache hits:'.$cache->getHits();

// Displays cache misses
echo 'Cache misses:'.$cache->getMisses();

// You can retrieve all of the above statistics at once
var_dump($cache->getInfo('test.object'));

// Displays all cached variable names
var_dump($cache->getKeys());

// Displays all cached variable names that were read in current runtime session
var_dump($cache->getReadKeys());

// Retrieves all tag values currently in use
var_dump($cache->getAllTags());

```
