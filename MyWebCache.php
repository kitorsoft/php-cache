<?php

class MyWebCache
{
  /*
  Usage:
  MyWebCache::$cachetime = ... // cache life time in seconds, defaults to 4h
  MyWebCache::$cacheFolder = ... // absolute path of folder cache files should be written to.
                                    Will be created, if it does not exist, and permissions permit it.
                                    Defaults to _DIR__ . '/cache'
  MyWebCache::setKey(...) // set they key for the current request.
                             Defaults to current URL including GET parameters (REQUEST_URI).
                             Must not contain any newlines!
                             You may wanto to set this yourself, if you use POST.
  MyWebCache::setKey(...) // get the current key (see above).
  
  MyWebCache::tryGetFromCache() // attempt to read results from cache. Will write results to response
                                   (headers not included) and exit the script, if a matching cache entry
                                   is found. Otherwise the script just continues, and all outpout will be
                                   buffered from here on.
  MyWebCache::writeCache() // writes all buffered outout to cache and flushes the output buffer.
  MyWebCache::cleanupThisCache() // will delete the cache file matching the current key,
                                    regardless of its life time, if it exists.
  MyWebCache::cleanupCache() // will expire all cache files that have exceeded teir life time.

  
  Typical program flow:
  1. write headers
  2. MyWebCache::tryGetFromCache()
  3. calculate and write response
  4. MyWebCache::writeCache()
  
  As any hash, MD5 can produce the same value for different inputs. Therefore the key
  is written to the last line of the cache file.
  Up to 10 cache files may be created for the same MD5 hash, if this limit is exceeded,
  caching will not happen.
  
  For testing on PHP Sandbox:
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
    //echo file_get_contents("/tmp/cache/4e4d6c332b6fe62a63afe56171fd3725-000");
  
  // $cachefile is based on the page and all its paramaters
  //   this is too long for file names (limit is 255 bytes), so we use:
  // $cachefileMD5 contains the MD5 hash of $cachefile. This serves as the file name.
  // The cache file will contain the output and a last line that contains $cachefile.
  //   This way, we can be sure to pick the correct file later on.
  
  */

  // Set lifetime (seconds)
  public static $cachetime = 4 * 60 * 60; // 4h

  public static $cacheFolder = __DIR__ . '/cache';

  private static $cacheFileKey = null;
  public static function setKey($key)
  {
    self::$cacheFileKey = $key;
  }
  
  public static function getKey()
  {
    if(!self::$cacheFileKey)
    {
      self::$cacheFileKey = $_SERVER['REQUEST_URI'];
    }
    
    return self::$cacheFileKey;
  }
  
  private static function getKeyMD5()
  {
    return self::$cacheFolder . '/' . md5(self::getKey());
  }

  private static function createCacheFolder()
  {
    // create cache folder if necessary
    if(!is_dir(self::$cacheFolder))
    {
      // try to create it, ignore any errors
      @mkdir(self::$cacheFolder, 0750);
    }
    
    return is_dir(self::$cacheFolder) && is_writable(self::$cacheFolder);
  }

  private static function getLastLine($file)
  {
    $lines = file($file);
    return $lines[count($lines)-1];
  }

  private static function findCacheFile()
  {
    $files = glob(self::getKeyMD5() . "*");
    foreach($files as $file)
    {
      if(self::getLastLine($file) == self::$cacheFileKey)
      {
        return $file;
      }
    }
    
    return false;
  }

  private static function newCacheFileName()
  {
    for($i = 0; $i < 10; $i++)
    {
      $f = sprintf(self::getKeyMD5() . "-%03d", $i);
      if(!file_exists($f))
      {
        return $f;
      }
    }
    
    return false;
  }

  private static function removeLastLine($s)
  {
    $a = explode(PHP_EOL, $s);
    array_pop($a);
    return join(PHP_EOL, $a);
  }


  private static function getHeader($which, $headers)
  {
    $retVal = false;
    
    foreach($headers as $h)
    {
      $harr = explode(":", $h);
      if(strtolower($harr[0]) == strtolower($which))
      {
        $retVal = explode(";", $harr[1])[0];
        break;
      }
    }
    
    return $retVal;
  }
  public static function tryGetFromCache()
  {
    // make sure the cache exists
    if(!self::createCacheFolder())
    {
      return; // no cache, proceed without
    }
    
    $file = self::findCacheFile();
    if($file)
    {
     // Serve from the cache if it is younger than $cachetime
      if(filesize($file) > 0 && time() < (filemtime($file) + self::$cachetime))
      {
        // The page has been cached from an earlier request
        // Output the contents of the cache file
        $cached = file_get_contents($file);
        // remove key info
        $cached = self::removeLastLine($cached);
        
        /* Add a timestamp comment, if possible, so the fact that the result was
           served from the cache is visible in the response.
           Of course, this can only be done for a few file types without
           interferinng. */
        $cacheStamp = 'From cache at: ' . date('H:i', filemtime($file));
        $ct = self::getHeader("Content-Type", headers_list());
        switch($ct)
        {
          case 'text/html': 
          case 'text/xml':
          case 'application/xhtml+xml':
          case 'application/xml':   
            // append <!-- -->
            $cached = $cached . PHP_EOL . '<!-- ' . $cacheStamp . ' -->';
            break;
          case 'text/javascript':
          case 'text/css':
            // append  /* */
            $cached = $cached . PHP_EOL . '/* ' . $cacheStamp . ' */';
            break;
          case 'text/calendar':
            // inserte COMMENT: before END:VCALENDAR
            $cached = preg_replace('/(END:VCALENDAR)\.*/', 'COMMENT:' . $cacheStamp . PHP_EOL . '${1}', $cached, 1);
            break;
          default:
            // do nothing,we do not want to mess with potential data
        }
                
        // output cached content
        echo $cached;
        // Exit the script, so that the rest isn't executed
        exit;
      }
      else // file exists, but is too old, delete it
      {
        unlink($file);
      }
    }

    // start the output buffer
    ob_start();
  }
  

##################################

  public static function writeCache()
  {
    // make sure the cache exists
    if(self::createCacheFolder() && $f = self::newCacheFileName())
    {
      // Open the cache file for writing
      $fp = @fopen($f, 'w'); 
      // Save the contents of output buffer to the file
      @fwrite($fp, ob_get_contents());
      @fwrite($fp, PHP_EOL . self::$cacheFileKey);
      // Close the file
      @fclose($fp);         

      // Send the output to the browser
      ob_end_flush();
    }    
  }

#########################
  public static function cleanupThisCache()
  {
    $file = self::findCacheFile();
    if($file)
    {
      unlink($file);
    }
  }

  public static function cleanupCache()
  {
    // make sure the cache exists
    if(!self::createCacheFolder())
    {
      return; // no cache, nothing to delete
    }

    foreach (new DirectoryIterator(self::$cacheFolder) as $fileInfo)
    {
      if ($fileInfo->isDot())
      {
        continue;
      }
      if ($fileInfo->isFile() && time() > ($fileInfo->getCTime() + self::$cachetime))
      {
        unlink($fileInfo->getRealPath());
      }
    }
  }
}
?>
