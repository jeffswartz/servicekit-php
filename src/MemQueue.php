<?php

/* Credit: https://github.com/abhinavsingh/memq */

namespace ServiceKit;

class MemQueue {

    private $mem;
    private $name;
    private static $memqTtl = 0;

    public function __construct($name, $pool, $username=null, $password=null) {

        $this->name = $name;

        $this->mem = new \Memcached;
        $this->mem->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);

        $servers = explode(",", $pool);
        foreach($servers as $server) {
            list($host, $port) = explode(":", $server);
            $this->mem->addServer($host, $port);
        }

        if (!empty($username) && !empty($password)) {
            $this->mem->setSaslAuthData($username, $password);
        }
    }

    public function isEmpty() {
        $head = $this->mem->get($this->name."_head");
        $tail = $this->mem->get($this->name."_tail");

        if ($head >= $tail || $head === false || $tail === false) {
            return true;
        } else {
            return false;
        }
    }

    public function dequeue($after_id=false, $till_id=false) {

        if ($after_id === false && $till_id === false) {
            $tail = $this->mem->get($this->name . "_tail");
            if (($id = $this->mem->increment($this->name."_head")) === false) {
                return false;
            }

            if ($id <= $tail) {
                return $this->mem->get($this->name . "_" . ($id - 1));
            } else {
                $this->mem->decrement($this->name . "_head");
                return false;
            }
        } else if ($after_id !== false && $till_id === false) {
            $till_id = $this->mem->get($this->name . "_tail");
        }

        $item_keys = array();
        for($i = $after_id + 1; $i <= $till_id; $i++) {
            $item_keys[] = $this->name . "_" . $i;
        }
        $null = null;

        return $this->mem->getMulti($item_keys, $null, \Memcached::GET_PRESERVE_ORDER);
    }

    public function enqueue($item) {
        $id = $this->mem->increment($this->name . "_tail");

        if ($id === false) {
            if ($this->mem->add($this->name . "_tail", 1, self::$memqTtl) === false) {
                $id = $this->mem->increment($this->name . "_tail");
                if($id === false) {
                    return false;
                }
            } else {
                $id = 1;
                $this->mem->add($this->name . "_head", $id, self::$memqTtl);
            }
        }

        if ($this->mem->add($this->name . "_" . $id, $item, self::$memqTtl) === false) {
            return false;
        }

        return $id;
    }

}
