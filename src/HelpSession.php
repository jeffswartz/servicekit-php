<?php

namespace ServiceKit;

class HelpSession {
    private $sessionId;
    private $customerName;
    private $problemText;

    const KEY_PREFIX = 'helpsession_';

    public static function create($sessionId, $customerName, $problemText) {
        $helpSession = new HelpSession($sessionId, $customerName, $problemText);
        $helpSession->memcached = MemcachedFactory::getFactory()->getMemcached();
        return $helpSession->save();
    }

    public static function findBySessionId($sessionId) {
        return self::memcached()->get(self::KEY_PREFIX . $sessionId);
    }

    private static function memcached() {
        return MemcachedFactory::getFactory()->getMemcached();
    }

    private function __construct($sessionId, $customerName, $problemText) {
        $this->sessionId = $sessionId;
        $this->customerName = $customerName;
        $this->problemText = $problemText;
    }

    public function save() {
        // TODO: consider using cas_token
        $success = self::memcached()->set(self::KEY_PREFIX . $this->sessionId, $this);
        return $success ? $this : false;
    }

    public function getSessionId() {
        return $this->sessionId;
    }

    public function getCustomerName() {
        return $this->customerName;
    }

    public function getProblemText() {
        return $this->problemText;
    }
}
