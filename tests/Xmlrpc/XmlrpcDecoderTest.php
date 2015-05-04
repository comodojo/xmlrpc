<?php namespace Comodojo\Xmlrpc\Tests;

class XmlrpcDecoderTest extends \PHPUnit_Framework_TestCase {

    public function testDecodeMethodCallListMethods() {
        
        $decoder = new \Comodojo\Xmlrpc\XmlrpcDecoder();

        $xml_data = file_get_contents(__DIR__."/../resources/methodCall_listMethods.xml");

        $decoded = $decoder->decodeCall( $xml_data );
        
        $this->assertInternalType('array', $decoded);

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
        
        $this->assertInternalType('array', $decoded);

        foreach ($decoded[0] as $method) {
            
            $this->assertContains($method, $methods);      

        }

    }

}
