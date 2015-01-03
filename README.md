# comodojo/xmlrpc

Yet another php xmlrpc decoder/encoder.

Main features:

- support for `nil` and `ex:nil`
- implements true, XML compliant, HTML numeric entities conversion
- support for CDATA values

# Installation

- Using Composer

	Install [composer](https://getcomposer.org/), then:

	`` composer require comodojo/xmlrpc 1.0.* ``

-	Manually

	Download zipball from GitHub, extract it, include `src/XmlrpcEncoder.php`, `src/XmlrpcDecoder.php` and `src/Exception/XmlrpcException.php` in your project.

# Usage

## Encoding request/response

-	Create an encoder instance:

	```php
	
	// create an encoder instance
	$encoder = new \Comodojo\Xmlrpc\XmlrpcEncoder();

	// (optional) set character encoding
	$encoder->setEncoding("utf-8");

	// (optional) use ex:nil instead of nil
	$encoder->useExNil();

	// (optional) declare special types in $data
	$encoder->setValueType($data['a_value'], "base64");
	$encoder->setValueType($data['b_value'], "datetime");
	$encoder->setValueType($data['c_value'], "cdata");
	
	// Wrap actions in a try/catch block (see below)
	try {

		/* encoder actions */

	} catch (\Comodojo\Exception\XmlrpcException $xe) {

		/* someting goes wrong during encoding */

	} catch (\Exception $e) {
		
		/* generic error */

	}

	```

-	single call:

	```php
	
	$call = $encoder->encodeCall("my.method", array("user"=>"john", "pass" => "doe")) ;

	```

-	multicall:

	```php
	
	$multicall = $encoder->encodeMulticall( array (
		"my.method" => array( "user"=>"john", "pass" => "doe" ),
		"another.method" => array( "value"=>"foo", "param" => "doe" ),
	);

	```

-	single call success response

	```php
	
	$response = $encoder->encodeResponse( array("success"=>true) );

	```

-	single call error response

	```php
	
	$error = $encoder->encodeError( 300, "Invalid parameters" );

	```

-	multicall success/error (faultString and faultCode should be explicitly declared in $data)

	```php
	
	$values = $encoder->encodeResponse( array(

		array("success"=>true),

		array("faultCode"=>300, "faultString"=>"Invalid parameters")

	);

	```

## Decoding 

-	create a decoder instance:

	```php
	
	// create a decoder instance
	$decoder = new \Comodojo\Xmlrpc\XmlrpcDecoder();
	
	// Wrap actions in a try/catch block (see below)
	try {

		/* decoder actions */

	} catch (\Comodojo\Exception\XmlrpcException $xe) {

		/* someting goes wrong during decoding */

	}

	```

-	decode single call

	```php
	
	$incoming_call = $decoder->decodeCall( $xml_singlecall_data );

	```

-	decode multicall

	```php
	
	$incoming_multicall = $decoder->decodeMulticall( $xml_multicall_data );

	```

-	decode response
	
	```php
	
	$returned_data = $decoder->decodeResponse( $xml_response_data );

	```
