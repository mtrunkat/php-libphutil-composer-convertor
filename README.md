Convertor of Libphutil library to  Composer compatible version
------

Facebook's [Libphutil](https://github.com/facebook/libphutil) library uses it's own autoloader and therefore is not compatible with [Composer](https://getcomposer.org). This project implements simple command line script to make Libphutil Composer compatible.

The main problem is that Composer doesn't support autoloading of functions so this script converts all the functions to static methods of newly created classes.

This convertor moves all the **classes** into **Facebook\Libphutil** namespace and each function **[functionname]** located in [filename].php converts to static method **Facebook\Libphutil\Functions\[filename]::[functionname]**.

Final library as Composer package can by found here https://github.com/mtrunkat/php-libphutil-composer.

### How it works

* Download Libphutil library from https://github.com/facebook/libphutil
* Clone or download this project 
* You need to have [Composer](https://getcomposer.org) installed
* Go to convertor directory and install dependencies via Composer:

		php composer.phrar install

* Convert downloaded Libphutil library to composer compatible version

		php [path-to-convertor]/console.php convert [path-to-libphutil] [path-to-target-directory]


### Example

Original use:
```php
<?php
	
require_once 'path/to/libphutil/src/__phutil_library_init__.php';

$futures = array();
$futures['test a'] = new ExecFuture('ls');
$futures['test b'] = new ExecFuture('ls -l -a');
	
foreach (Futures($futures) as $dir => $future) {
    list($stdout, $stderr) = $future->resolvex();
	
    print $stdout;
}
```
Composer version use:
```php
<?php

require_once 'vendor/autoload.php';
	
use Facebook\Libphutil\ExecFuture;
use Facebook\Libphutil\Functions\functions;
	
$futures = array();
$futures['test a'] = new ExecFuture('ls');
$futures['test b'] = new ExecFuture('ls -l -a');
	
foreach (functions::Futures($futures) as $dir => $future) {
    list($stdout, $stderr) = $future->resolvex();
	
    print $stdout;
}
```

You can see that class **ExecFuture** in now in **Facebook\Libphutil** namespace and function **Futures()** originally located in file **functions.php** is now static method of class **Facebook\Libphutil\Functions\functions**.
