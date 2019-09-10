<?php

class CacheException extends Exception {}

class Cache
{
    static $Directory = './cache';

    private static function Path(string $file) { return self::$Directory . "/$file"; }

    static function Remove(string $cache)
    {
        unlink(self::Path($cache));
    }

    static function Set(string $cache, $value)
    {
        $path = self::Path($cache);
        $dir = dirname($path);

        if (!file_exists($dir) && !mkdir($dir, 0777, true))
        {
            throw new CacheException();
        }
        
        file_put_contents($path, json_encode($value));
    }

    static function Get(string $cache)
    {
        $path = self::Path($cache);

        if (file_exists($path)) return json_decode(file_get_contents($path), true);
    }
}

class MCache
{
    private const META = '921q783fh';
    private const EXP = 'expiration';
    private const ENC = 'encryption';
    
    const MINUTE = 60;
    const HOUR = 3600;
    const DAY = 86400;
    const WEEK = 604800;

    static function SetMeta(string $cache, array $values)
    {
        $meta = (array) Cache::Get(self::META);

        $meta[$cache] = $values;

        Cache::Set(self::META, $meta);
    }

    static function GetMeta(string $cache)
    {
        $meta = (array) Cache::Get(self::META);

        if (array_key_exists($cache, $meta)) return $meta[$cache];
        else return [];
    }

    static function Set(string $cache, $value, array $meta = [], int $expiration = NULL)
    {
        if (!is_null($expiration)) $meta[self::EXP] = time() + $expiration;

        Cache::Set($cache, $value);
        self::SetMeta($cache, $meta);
    }

    static function Get(string $cache)
    {
        if (is_null($c = Cache::Get($cache))) return NULL;

        $meta = self::GetMeta($cache);

        if (array_key_exists(self::EXP, $meta) && $meta[self::EXP] < time())
        {
            Cache::Remove($cache);
            return NULL;
        }

        return $c;
    }

    static function Exchange(string $cache, callable $value, array $meta, int $expiration = NULL)
    {
        if (is_null($existing = self::Get($cache))) return $existing;

        $new = $value();
        self::Set($cache, $new, $meta, $expiration);
        return $new;
    }

    static function Pull(string $cache)
    {
        return new CacheItem($cache);
    }
}

class CacheItem
{
    public $Cache;
    public $Value;
    public $Meta;

    function __construct(string $cache)
    {
        $this->Cache = $cache;
        $this->Value = MCache::Get($cache);
        $this->Meta = MCache::GetMeta($cache);
    }

    function Set($value, int $expiration = NULL, bool $encrypt = false)
    {
        MCache::Set($this->Cache, $value, [], $expiration, $encrypt);
    }

    function SetMeta(array $values) 
    { 
        MCache::SetMeta($this->Cache, $values);
    }

    function GetMeta(string $key)
    {
        if (array_key_exists($key, $this->Meta)) return $this->Meta[$key];
    }
}
