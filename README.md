# laravelicious

Laravelicious is a Laravel package for wrap all the Delicious API methods.

Require this package with composer using the following command:

``` composer require tr0n/laravelicious```.

## Usage:

First, you need to add the connection params in the confif, for doing this first you need to publish the config file:

```php artisan config:publish```

After that, a config file named ```default.php``` is generated inside a ```/app/config/packages/tr0n/laravelicious/``` folder.
Add here the user and password.


All the available Delicious API calls ar mapped.


