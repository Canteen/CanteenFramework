#CanteenFramework

Small PHP framework for building JSON-driven, stateless websites.

##Usage

For an example of usage please see the [Canteen Boilerplate](https://github.com/Canteen/CanteenBoilerplate) project. 

### Contents of index.php

```php
use Canteen\Site;

$site = new Site(array(
	'dbUsername' => 'user',
	'dbPassword' => 'pass1234',
	'dbName' => 'my_database',
));

$site->render();
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