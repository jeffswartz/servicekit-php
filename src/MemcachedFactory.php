<?php

namespace ServiceKit;

class MemcachedFactory {

    private static $factory;
    private $memcached;
    private $config;

    const PERSISTANCE_ID = 'servicekitmemcachedfactory';

    public static function configureFactory($config) {
        self::$factory = new MemcachedFactory($config);
    }

    public static function getFactory() {
        if (!self::$factory) {
            throw new Exception('The factory must be configured');
        }
        return self::$factory;
    }

    public function __construct($config) {
        $this->config = $config;
    }

    public function getMemcached() {
        if (empty($this->memcached)) {
            $memcached = new \Memcached(self::PERSISTANCE_ID);
            $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);

            $servers = explode(",", $config['pool']);
            foreach($servers as $server) {
                list($host, $port) = explode(":", $server);
                $memcached->addServer($host, $port);
            }

            if (!empty($config['username']) && !empty($config['password'])) {
                $memcached->setSaslAuthData($config['username'], $config['password']);
            }
            $this->memcached = $memcached;
        }
        return $this->memcached;
    }

}
