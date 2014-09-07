DeploYii
========

Fetch, setup, automate and deploy your projects!<br>
...powered by PHP & the Yii Framework.

[![Latest Stable Version](https://poser.pugx.org/giovdk21/deployii/v/stable.svg)](https://packagist.org/packages/giovdk21/deployii) [![License](https://poser.pugx.org/giovdk21/deployii/license.svg)](https://packagist.org/packages/giovdk21/deployii)

(once ready) you'll be able to use DeploYii either as a simple task runner to automate your project setup and configuration
or as deployment solution to package and deploy your code.


Requirements
-------------

- PHP 5.4+
- [Composer](https://getcomposer.org/)
- Git (command line)


Getting started
-------------

1. get DeploYii: `composer create-project giovdk21/deployii deployii ~0.5`
2. go inside the deployii folder (`cd deployii/`)
3. run `./deployii` to check the available options
4. run `./deployii fetch example_basic` to try out the basic example
5. run `./deployii run ~/.deployii/workspace/example_basic_[...]/basicExample/` to run it again, without re-downloading it
6. run `./deployii run ~/.deployii/workspace/example_basic_[...]/basicExample/ clean` to run the clean target
7. run `./deployii init ~/.deployii/workspace/helloWorld/` to create a new build script

**For more information see the documentation on the [wiki](https://github.com/giovdk21/deployii/wiki).**

```php
return [

  'deployiiVersion' => '0.5.0',

  'require' => [],

  'params' => [
    'username' => 'world',
  ],

  'targets' => [
    'default' => [
      ['out', 'Hello {{username}}!'],
    ],
  ],
];
```

...want to see more? [Click here](https://github.com/giovdk21/deployii-examples/blob/master/basicExample/deployii/build.php) :wink:

Available features
-------------

- build script based on a simple PHP array
- fetch your code from git and store it into a unique workspace directory
- build script parameters
- build script requirements
- user defined command and recipes
- output text to the user
- require user input (prompt, confirm and select between multiple values)
- non interactive mode
- override default parameters with command line options
- save / load information from json files
- if / else statement
- replace placeholders with build parameters values (both strings and arrays are supported)
- check the build script compatibility with the current DeploYii version and return the list of non-backward compatible changes
- call a target from another (chaining)
- execute shell commands
- copy file
- copy folders
- create folder
- remove file
- remove folder (recursive)
- move files and folders
- set files and folders permissions
- archive/compress files and folders
- SFTP support (put, get, mkdir, chmod, mv, rm, rmdir, ...)
- SFTP authentication via RSA key
- SFTP exec (exec remote command via ssh)
- FTP support (passive mode only)
- multiple SFTP / FTP connections
- replace in files
- path aliases to the workspace and the build script folders
- parameters placeholders also work in paths
- build execution log
- dry run mode



Planned features
-------------

- sftp / ftp file transfer commands
- execute remote commands over ssh
- command to run composer which downloads it if not present
- command to fetch (clone) / update from git
- command to self update DeploYii
- codeception related commands
- other CI related commands
- JS and CSS minification and concatenation
- workspaces cleanup
- project management user interface
- database based project information
- etc.


Community
-------------

* Chat: [![Gitter chat](https://badges.gitter.im/giovdk21/deployii.png)](https://gitter.im/giovdk21/deployii)
* Forum thread: [DeploYii on the Yii forum](http://www.yiiframework.com/forum/index.php/topic/56289-deployii-task-runner-and-deployment-pre-release/)
