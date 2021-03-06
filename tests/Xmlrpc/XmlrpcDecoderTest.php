<?php

use \PHPUnit\Framework\TestCase;
class XmlrpcDecoderTest extends TestCase {

    public function testDecodeMethodCallListMethods() {
        
        $decoder = new \Comodojo\Xmlrpc\XmlrpcDecoder();

        $xml_data = file_get_contents(__DIR__."/../resources/methodCall_listMethods.xml");

        $decoded = $decoder->decodeCall( $xml_data );
        
        $this->assertIsArray($decoded);

        $this->assertEquals('system.listMethods', $decoded[0]);

    }

    public function testDecodeMethodResponseListMethods() {
        
        $methods = array(
            "system.listMethods",
            "system.methodSignature",
            "system.methodHelp",
            "system.multicall",
            "system.shutdown",
            "sample.add"
        );

        $decoder = new \Comodojo\Xmlrpc\XmlrpcDecoder();

        $xml_data = file_get_contents(__DIR__."/../resources/methodResponse_listMethods.xml");

        $decoded = $decoder->decodeResponse( $xml_data );
        
        $this->assertIsArray($decoded);

        foreach ($decoded as $method) {
            
            $this->assertContains($method, $methods);      

        }

    }

    public function testDecodeErrorResponse() {
        
        $decoder = new \Comodojo\Xmlrpc\XmlrpcDecoder();

        $xml_data = file_get_contents(__DIR__."/../resources/methodResponse_error.xml");

        $decoded = $decoder->decodeResponse( $xml_data );
        
        $this->assertIsArray($decoded);

        $this->assertTrue($decoder->isFault());

    }

    public function testDecodeMultiCallRequest() {

        $decoder = new \Comodojo\Xmlrpc\XmlrpcDecoder();

        $xml_data = file_get_contents(__DIR__."/../resources/methodCall_systemMulticall.xml");

        $decoded = $decoder->decodeCall( $xml_data );

        $this->assertIsArray($decoded);

        foreach ($decoded as $call) {
            
            $this->assertIsArray($call);

        }

    }

    public function testDecodeInvalidMultiCallRequest() {

        $decoder = new \Comodojo\Xmlrpc\XmlrpcDecoder();

        $xml_data = file_get_contents(__DIR__."/../resources/methodCall_invalidSystemMulticall.xml");

        $decoded = $decoder->decodeCall( $xml_data );

        $this->assertIsArray($decoded);

        foreach ($decoded as $index => $call) {
            
            if ( $index == 0 ) {

                $this->assertNull($call);

            } else {

                $this->assertIsArray($call);

            }

        }

    }

    public function testDecodeMultiCallResponse() {

        $decoder = new \Comodojo\Xmlrpc\XmlrpcDecoder();

        $xml_data = file_get_contents(__DIR__."/../resources/methodResponse_systemMulticall.xml");

        $decoded = $decoder->decodeResponse( $xml_data );
        
        $this->assertIsArray($decoded);

    }

    public function testDecodeInvalidValue() {

        $this->expectException("\Comodojo\Exception\XmlrpcException");

        $decoder = new \Comodojo\Xmlrpc\XmlrpcDecoder();

        $xml_data = file_get_contents(__DIR__."/../resources/methodResponse_invalid.xml");

        $decoded = $decoder->decodeResponse( $xml_data );

    }

}
