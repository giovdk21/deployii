<?php

/**
 * Build script configuration file
 *
 * This file:
 * - is loaded by the task runner
 * - configuration is injected into the build parameters according to the selected environment
 * - is, by default, not committed to VCS (see .gitignore)
 * - configuration defined in this file will be obfuscated in the log and output
 */


return [

    'defaultEnvironment' => 'dev',
    'environments'       => ['dev' => 'Development', 'prod' => 'Production'],
    'config'             => [

        'common' => [ // default for every environment

        ],
        'dev'    => [

        ],
        'prod'   => [

        ],

    ],

];