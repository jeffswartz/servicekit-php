<?php

/* Credit: https://github.com/abhinavsingh/memq */

namespace ServiceKit;

class MemQueue {

    private $mem;
    private $name;
    private static $memqTtl = 0;

    public function __construct($name) {
        $this->name = $name;
        $this->mem = MemcachedFactory::getFactory()->getMemcached();
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

    public function dequeue() {
        $tail = $this->mem->get($this->name . "_tail");

        if (($id = $this->mem->increment($this->name."_head")) === false) {
            return false;
        }

        if ($id - 1 <= $tail) {
            return $this->mem->get($this->name . "_" . ($id - 1));
        } else {
            $this->mem->decrement($this->name . "_head");
            return false;
        }
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
