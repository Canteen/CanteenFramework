#Canteen Framework

Small PHP framework for building JSON-driven, stateless websites. For documentation of the codebase, please see [Canteen Framework docs](http://canteen.github.io/CanteenFramework/).

##Usage

For an example of usage please see the [Canteen Boilerplate](https://github.com/Canteen/CanteenBoilerplate) project. 

##Installation

Install is available using [Composer](http://getcomposer.org).

```bash
composer require canteen/framework dev-master
```
Your site should contain the following files at the root directory:

+ index.php
+ config.php
+ .htaccess

###Contents of index.php

Include the Composer autoloader in your index and render the new site.

```php
require 'vendor/autoload.php';
$site = new Canteen\Site();
$site->render();
```

###Contents of config.php

Setup your deployment of the site. The minimum required settings are specified below.

```php
return array(
	'dbUsername' => 'user',
	'dbPassword' => 'pass1234',
	'dbName' => 'my_database',
);
```

###Contents of .htaccess

Canteen requires that an .htaccess file be installed alongside your index.php. This manages all of the URL requests and passes them to the site. The example below is assuming the index.php is at the root-domain of your site.

```apache
DirectoryIndex index.php

<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase /
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule . /index.php [L]
</IfModule>

#Unauthorized
ErrorDocument 401 /401

#Forbidden
ErrorDocument 403 /403

#Not Found
ErrorDocument 404 /404

#Internal
ErrorDocument 500 /500
```

###Rebuild Documentation

This library is auto-documented using [YUIDoc](http://yui.github.io/yuidoc/). To install YUIDoc, run `sudo npm install yuidocjs`. Also, this requires the project [CanteenTheme](http://github.com/Canteen/CanteenTheme) be checked-out along-side this repository. To rebuild the docs, run the ant task from the command-line. 

```bash
ant docs
```

##License##

Copyright (c) 2013 [Matt Karl](http://github.com/bigtimebuddy)

Released under the MIT License.