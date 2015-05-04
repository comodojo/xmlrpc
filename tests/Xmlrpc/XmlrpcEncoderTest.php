<?php namespace Comodojo\Xmlrpc\Tests;

class XmlrpcEncoderTest extends \PHPUnit_Framework_TestCase {

    public function testEncodeMethodCallListMethods() {
        
        $encoder = new \Comodojo\Xmlrpc\XmlrpcEncoder();

        $call = $encoder->encodeCall("system.listMethods");

        $this->assertXmlStringEqualsXmlFile(__DIR__."/../resources/methodCall_listMethods.xml", $call);

    }

    public function testEncodeMethodResponseListMethods() {
        
        $methods = array(
            "system.listMethods",
            "system.methodSignature",
            "system.methodHelp",
            "system.multicall",
            "system.shutdown",
            "sample.add"
        );

        $encoder = new \Comodojo\Xmlrpc\XmlrpcEncoder();

        $response = $encoder->encodeResponse( $methods );

        $this->assertXmlStringEqualsXmlFile(__DIR__."/../resources/methodResponse_listMethods.xml", $response);

    }

}
