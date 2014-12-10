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
        error_log('dequeue');
        $tail = $this->mem->get($this->name . "_tail");
        error_log('tail is ' . $tail);

        if (($id = $this->mem->increment($this->name."_head")) === false) {
            error_log('head could not be incremented. giving up on dequeue.');
            return false;
        }
        error_log('incremented head to ' . $id);

        if ($id - 1 <= $tail) {
            error_log('head is within range');
            // check if this is a deleted item, and if it is, call dequeue again
            $item = $this->mem->get($this->name . "_" . ($id - 1));
            error_log('item at former head ' . $item);
            if ($item == false) {
                error_log('could not read item. giving up on dequeue');
                return false;
            }
            if ($item == self::MARK_DELETED) {
                error_log('item was marked deleted, recursing');
                return $this->dequeue();
            } else {
                error_log('item dequeued');
                return $item;
            }
        } else {
            $revertedHead = $this->mem->decrement($this->name . "_head");
            error_log('head was outside of range. reverted to ' . $revertedHead . '. giving up on dequeue.');
            return false;
        }
    }

    public function enqueue($item) {
        error_log('enqueue ' . $item);
        $id = $this->mem->increment($this->name . "_tail");

        if ($id === false) {
            if ($this->mem->add($this->name . "_tail", 1, self::$memqTtl) === false) {
                error_log('tail could not be set to 1');
                $id = $this->mem->increment($this->name . "_tail");
                error_log('tail incremented to ' . $id);
                if($id === false) {
                    error_log('giving up on enqueue');
                    return false;
                }
            } else {
                error_log('tail set to 1');
                $id = 1;
                $this->mem->add($this->name . "_head", $id, self::$memqTtl);
                error_log('head set to 1');
            }
        }
        error_log('tail incremented to ' . $id);

        if ($this->mem->set($this->name . "_" . $id, $item, self::$memqTtl) === false) {
            return false;
        }
        error_log('item enqueued at ' . $id);

        return $id;
    }

    public function delete($id) {
        error_log('delete ' . $id);
        // if its the head, just move the head
        $head = $this->mem->get($this->name."_head");
        error_log('head is ' . $head);
        if ($id == $head) {
            // NOTE: this may return -1 even though it was successful, unsuccessful is 'false'
            $newHead = $this->mem->increment($this->name . "_head");
            error_log('head was incremented to ' . $newHead);
            return $newHead;
        }

        // if its the tail, just move the tail
        $tail = $this->mem->get($this->name."_tail");
        error_log('tail is ' . $tail);
        if ($id == $tail) {
            $newTail = $this->mem->decrement($this->name . "_tail");
            error_log('tail was decremented to ' . $newTail);
            return $newTail;
        }

        // if its in between, mark it as deleted
        $setResult = $this->mem->set($this->name . "_" . $id, self::MARK_DELETED, self::$memqTtl);
        error_log('item was marked as deleted ' . $setResult);
        return $setResult;
    }

}
