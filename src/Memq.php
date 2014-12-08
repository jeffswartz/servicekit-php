<?php

/* Credit: https://github.com/abhinavsingh/memq */

namespace ServiceKit;
	
use \werx\Config\Providers\ArrayProvider;
use \werx\Config\Container;

	class Memq {
		
		private $mem = NULL;
        private static $memqTtl = 0;
		
        public function __construct($pool, $username=null, $password=null) {
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
		
		public function is_empty($queue) {
			$head = $this->mem->get($queue."_head");
			$tail = $this->mem->get($queue."_tail");
			
			if($head >= $tail || $head === FALSE || $tail === FALSE) 
				return TRUE;
			else 
				return FALSE;
		}

		public function dequeue($queue, $after_id=FALSE, $till_id=FALSE) {
			if($after_id === FALSE && $till_id === FALSE) {
				$tail = $this->mem->get($queue."_tail");
				if(($id = $this->mem->increment($queue."_head")) === FALSE) 
					return FALSE;
			
				if($id <= $tail) {
					return $this->mem->get($queue."_".($id-1));
				}
				else {
					$this->mem->decrement($queue."_head");
					return FALSE;
				}
			}
			else if($after_id !== FALSE && $till_id === FALSE) {
				$till_id = $this->mem->get($queue."_tail");	
			}
			
			$item_keys = array();
			for($i=$after_id+1; $i<=$till_id; $i++) 
				$item_keys[] = $queue."_".$i;
			$null = NULL;
			
			return $this->mem->getMulti($item_keys, $null, \Memcached::GET_PRESERVE_ORDER); 
		}
		
		public function enqueue($queue, $item) {
			$id = $this->mem->increment($queue."_tail");
			if($id === FALSE) {
				if($this->mem->add($queue."_tail", 1, self::$memqTtl) === FALSE) {
					$id = $this->mem->increment($queue."_tail");
					if($id === FALSE) 
						return FALSE;
				}
				else {
					$id = 1;
					$this->mem->add($queue."_head", $id, self::$memqTtl);
				}
			}
			
			if($this->mem->add($queue."_".$id, $item, self::$memqTtl) === FALSE) 
				return FALSE;
			
			return $id;
		}
		
	}
