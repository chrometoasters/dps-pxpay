xplore-dps-pxpay
================

PHP cURL implementation of DPS PxPay 2.0 API
http://www.paymentexpress.com/Technical_Resources/Ecommerce_Hosted/PxPay

Instructions
------------

Include the library in your code:

```
include('dps-pxpay.php');
```

Instantiate a new instance of the class using your DPS user id and key:

```
$client = new \pxpay\PxPay($userId, $key);
```

Construct a new transaction request:
```
// URL to redirect to and handle the response
$returnUrl = 'http://localhost/test.php';

// Create a new instance of the request object
$request = new \pxpay\TransactionRequest();

// Set the required request parameters
$request
	->SetAmount('10.00')
	->SetCurrencyInput(\pxpay\DPS_CURRENCY::NewZealandDollar)
	->SetTransactionType(\pxpay\DPS_TRANSACTION_TYPES::Purchase)
	->SetUrlFail($url)
	->SetUrlSuccess($url);

// Send the request
$client->SendRequest($request);
```

Any errors in the request will result in a ```\pxpay\RequestException``` to be thrown.


Check for a reponse:
```
$response = $client->ProcessResponse();

if (is_null($response)) {
    // There is no result parameter in the URL to process
} else if ($response->WasSuccessful()) {
    // The transaction was a success!
} else {
    // Try again, n00b
}
```

License
-------
[MIT License](http://opensource.org/licenses/MIT)
