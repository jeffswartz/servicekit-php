<?php
/* -----------------------------------------------------------------------------------------------
 * OpenTok Default Configuration
 *
 * This configuration is used as fallback when no other environment has been chosen. As a default,
 * the values are read from the environment variables, and there is no need to change this file.
 * -----------------------------------------------------------------------------------------------*/
return array(
    'key' => getenv('OPENTOK_KEY'),
    'secret' => getenv('OPENTOK_SECRET')
);
