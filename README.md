#Canteen Framework

Small PHP framework for building JSON-driven, stateless websites. For documentation of the codebase, please see [Canteen Framework docs](http://canteen.github.io/CanteenFramework/).

##Usage

For an example of usage please see the [Canteen Boilerplate](https://github.com/Canteen/CanteenBoilerplate) project. 

##Installation

Install is available using [Composer](http://getcomposer.org).

```bash
composer require canteen/framework dev-master
```

###Contents of index.php

```php
// Include the Composer autoloader
require 'vendor/autoload.php';

// Create a new Canteen Site
$site = new Canteen\Site();

// Render the page
$site->render();
```

###Rebuild Documentation

This library is auto-documented using [YUIDoc](http://yui.github.io/yuidoc/). To install YUIDoc, run `sudo npm install yuidocjs`. Also, this requires the project [CanteenTheme](http://github.com/Canteen/CanteenTheme) be checked-out along-side this repository. To rebuild the docs, run the ant task from the command-line. 

```bash
ant docs
```

##License##

Copyright (c) 2013 [Matt Karl](http://github.com/bigtimebuddy)

Released under the MIT License.