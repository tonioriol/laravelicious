# Laravelicious: A Delicious Laravel package.

This is a Laravel package that wraps all the Delicious API methods.

[Delicious API docs page](https://github.com/SciDevs/delicious-api)

## Installation

Require this package with composer using the following command:

`composer require tr0n/laravelicious`.

## Configuration

First, you need to add the auth params in the config, to do this you will need to publish the config file first. Use the following command:

`php artisan config:publish tr0n/laravelicious`

After that, a config file named `general.php` is generated inside the `/app/config/packages/tr0n/laravelicious/` folder. Add the user and password parameters inside this file.

## Usage

All the available Delicious API calls are mapped.


For example:

### `Laravelicious::add()`

Add a new post to Delicious.

#### Arguments
 Type | Name | Description
------|------|-------------
array | $params (see below) |
string | $params['url'] | The url of the item (required).
string | $params['description'] | The description of the item (required).
string | $params['extended'] | Notes for the item.
array  | $params['tags'] | Tags for the item.
string | $params['dt'] | Datestamp of the item (format “CCYY-MM-DDThh:mm:ssZ”). Requires a LITERAL “T” and “Z” like in ISO8601 at http://www.cl.cam.ac.uk/~mgk25/iso-time.html for Example: 1984-09-01T14:21:31Z.
bool | $params['replace'] | Don’t replace post if given url has already been posted (Default to false).
bool | $params['shared'] | Make the item private (Default to true).

#### Returns

An array With `'success'`, `'message'` and  `'url'` fields.


All the methods follow the same structure. The arguments are passed as array, depending on the method some are optional and some required, this way we are able to pass mor flexibly the arguments that we want.

Same for returning values. All methods returns an array with a field 'success' with true on success and false on failure, a 'message' field parsed from the xml (if not exists on the delicious.com response, the field will be empty).

When there are trouble connecting to delicious.com, a DeliciousConnectionException will be thrown with some details about the attempts, the url and a message.
