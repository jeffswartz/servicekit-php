<?php
/* -----------------------------------------------------------------------------------------------
 * Redis Default Configuration
 *
 * This configuration is used as fallback when no other environment has been chosen. As a default,
 * the values are read from the environment variables, and there is no need to change this file.
 * -----------------------------------------------------------------------------------------------*/

return array(
  'host' => parse_url(getenv('REDISTOGO_URL'), PHP_URL_HOST),
  'port' => parse_url(getenv('REDISTOGO_URL'), PHP_URL_PORT),
  'password' => parse_url(getenv('REDISTOGO_URL'), PHP_URL_PASS),
  'prefix' => 'servicekit'
);
