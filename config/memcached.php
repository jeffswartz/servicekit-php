<?php

return array(
    'pool' => getenv('MEMCACHEDCLOUD_SERVERS'),
    'username' => getenv('MEMCACHEDCLOUD_USERNAME'),
    'password' => getenv('MEMCACHEDCLOUD_PASSWORD')
);
