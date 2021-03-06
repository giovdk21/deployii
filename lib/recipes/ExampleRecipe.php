<?php
/**
 * DeploYii - TestRecipe
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

use yii\helpers\Console;

return [

    'deployiiVersion' => '0.4.0',

    'require' => [],

    'params'  => [],

    'targets' => [
        'default' => [
            ['out', 'Built-in recipe example', Console::FG_CYAN],
        ],
    ],

];