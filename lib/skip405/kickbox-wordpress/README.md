# Kickbox Verifications using WordPress request functions

[![Packagist](https://img.shields.io/packagist/v/skip405/kickbox-wordpress)](https://packagist.org/packages/skip405/kickbox-wordpress)
[![Packagist](https://img.shields.io/packagist/dt/skip405/kickbox-wordpress)](https://packagist.org/packages/skip405/kickbox-wordpress)

This package provides a Kickbox Email Verification API-client that uses WordPress HTTP-requests functions. Ideal if you want to employ Kickbox email verification in a WordPress plugin or theme.

## Installation
Install via composer:

```
composer require skip405/kickbox-wordpress
```

## Examples

### Verify a single email address
```
// include your composer dependencies
require_once 'vendor/autoload.php';

$client = new Skip405\Kickbox\Client( "KICKBOX_API_KEY" );

$verified_email = $client->verify( "jane.doe@example.com" );
```

In the example above `$verified_email` will be an associative array with `success` and `data` properties. See [response examples](#response-examples) below.

### Batch verification
```
// include your composer dependencies
require_once 'vendor/autoload.php';

$client = new Skip405\Kickbox\Client( "KICKBOX_API_KEY" );

$batch = $client->verify_batch(
    array( "jane.doe@example.com", "john.doe@example.com" ),
    array( "filename" => "My batch filename" )
);
```

In the example above `$batch` will be an associative array with `success` and `data` properties. See [response examples](#response-examples) below.

### Check batch verification status
```
// include your composer dependencies
require_once 'vendor/autoload.php';

$client = new Skip405\Kickbox\Client( "KICKBOX_API_KEY" );

$batch = $client->verify_batch(
    array( "jane.doe@example.com", "john.doe@example.com" ),
    array( "filename" => "My batch filename" )
);

$batch_status = $client->check_batch( $batch['data']['body']['id'] );
```

In the example above `$batch_status` will be an associative array with `success` and `data` properties. See response examples below.

## Response examples

Failing requests return an associative array with `success` being `false` and `data` being an instance of `WP_Error` with the error details. E.g. a timeout error

```
Array
(
    [success] => 
    [data] => WP_Error Object
        (
            [errors] => Array
                (
                    [http_request_failed] => Array
                        (
                            [0] => cURL error 28: Operation timed out after 5000 milliseconds with 0 bytes received
                        )

                )
            [error_data] => Array()
            [additional_data:protected] => Array()
        )
)
```

Successful requests return an associative array with `success` being `true` and `data` being and array with `code`, `body` and `headers` properties. `body` contains the response from Kickbox.

### Successful email verification response

```
Array
(
    [success] => 1
    [data] => Array
        (
            [code] => 200
            [body] => Array
                (
                    [result] => deliverable
                    [reason] => accepted_email
                    [role] => 
                    [free] => 
                    [disposable] => 
                    [accept_all] => 
                    [did_you_mean] => 
                    [sendex] => 1
                    [email] => deliverable@example.com
                    [user] => deliverable
                    [domain] => example.com
                    [success] => 1
                    [message] => You are using Kickbox's sandbox API, which is used to test your integration against mock results.
                )
            [headers] => Array()
        )
)
```

### Successful batch verification response

```
Array
(
    [success] => 1
    [data] => Array
        (
            [code] => 200
            [body] => Array
                (
                    [id] => 42
                    [success] => 1
                    [message] => 
                )
            [headers] => Array()
        )
)
```

### Successful batch verification status response

```
Array
(
    [success] => 1
    [data] => Array
        (
            [code] => 200
            [body] => Array
                (
                    [id] => 42
                    [name] => My batch filename
                    [created_at] => 2022-05-14T09:18:51.000Z
                    [status] => completed
                    [error] => 
                    [download_url] => download URL for batch results
                    [stats] => Array
                        (
                            [deliverable] => 0
                            [undeliverable] => 1
                            [risky] => 0
                            [unknown] => 0
                            [sendex] => 0
                            [addresses] => 1
                        )

                    [duration] => 6000
                    [success] => 1
                    [message] => 
                )
            [headers] => Array()
        )
)

```