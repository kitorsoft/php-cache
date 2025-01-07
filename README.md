# php-cache
A cache class for PHP web pages.

## Usage
<code>
MyWebCache::$cachetime = ... // cache life time in seconds, defaults to 4h
MyWebCache::$cacheFolder = ... // absolute path of folder cache files should be written to.
                                  Will be created, if it does not exist, and permissions permit it.
                                  Defaults to _DIR__ . '/cache'
MyWebCache::setKey(...) // set they key for the current request.
                           Defaults to current URL including GET parameters (REQUEST_URI).
                           Must not contain any newlines!
                           You may wanto to set this yourself, if you use POST.
MyWebCache::setKey(...) // get the current key (see above).
<br/>
MyWebCache::tryGetFromCache() // attempt to read results from cache. Will write results to response
                                 (headers not included) and exit the script, if a matching cache entry
                                 is found. Otherwise the script just continues, and all outpout will be
                                 buffered from here on.
MyWebCache::writeCache() // writes all buffered outout to cache and flushes the output buffer.
MyWebCache::cleanupThisCache() // will delete the cache file matching the current key,
                                  regardless of its life time, if it exists.
MyWebCache::cleanupCache() // will expire all cache files that have exceeded teir life time.
</cocde>

## Typical program flow:

1. write headers
2. MyWebCache::tryGetFromCache()
3. calculate and write response
4. MyWebCache::writeCache()

The MD5 hash of the given key is used as the file name in the cache. This way, the length of the file name is independent of that of the key, so sometime long keys (e. g. all parameters of the request) need to be part of the key.

As any hash, MD5 can produce the same value for different inputs. Therefore the key
is written to the last line of the cache file.
Up to 10 cache files may be created for the same MD5 hash. If this limit is exceeded, the output will not be cached.

## Testing on https://onlinephp.io/
<code>
MyWebCache::$cacheFolder = "/tmp/cache";
//MyWebCache::$cachetime = 1;
MyWebCache::setKey("haha");
MyWebCache::tryGetFromCache();
echo "haha";
MyWebCache::writeCache();
//MyWebCache::cleanupCache();
//MyWebCache::cleanupThisCache();
$files = glob("/tmp/cache/*");
    foreach($files as $file)
    {
      echo "\r\n" . $file;
    }
//echo file_get_contents(glob("/tmp/cache/*")[0]);
</code>
