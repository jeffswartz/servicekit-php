<?php

/* Credit: https://github.com/abhinavsingh/memq */

namespace ServiceKit;

class MemQueue {

    private $mem;
    private $name;
    private static $memqTtl = 0;

    const MARK_DELETED = "VALUE_DELETED";

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
            // check if this is a deleted item, and if it is, call dequeue again
            $item = $this->mem->get($this->name . "_" . ($id - 1));
            if ($item == false) {
                return false;
            }
            if ($item == self::MARK_DELETED) {
                return $this->dequeue();
            } else {
                return $item;
            }
        } else {
            $revertedHead = $this->mem->decrement($this->name . "_head");
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

        if ($this->mem->set($this->name . "_" . $id, $item, self::$memqTtl) === false) {
            return false;
        }

        return $id;
    }

    public function delete($id) {
        // if its the head, just move the head
        $head = $this->mem->get($this->name."_head");
        if ($id == $head) {
            // NOTE: this may return -1 even though it was successful, unsuccessful is 'false'
            $newHead = $this->mem->increment($this->name . "_head");
            return $newHead;
        }

        // if its the tail, just move the tail
        $tail = $this->mem->get($this->name."_tail");
        if ($id == $tail) {
            $newTail = $this->mem->decrement($this->name . "_tail");
            return $newTail;
        }

        // if its in between, mark it as deleted
        $setResult = $this->mem->set($this->name . "_" . $id, self::MARK_DELETED, self::$memqTtl);
        return $setResult;
    }

}
