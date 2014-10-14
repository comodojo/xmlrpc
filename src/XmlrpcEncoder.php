<?php namespace Comodojo\Xmlrpc;

use \Comodojo\Exception\XmlrpcException;
use \SimpleXMLElement;
use \Exception;

/** 
 * XML-RPC encoder
 *
 * @package     Comodojo Spare Parts
 * @author      Marco Giovinazzi <info@comodojo.org>
 * @license     GPL-3.0+
 *
 * LICENSE:
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class XmlrpcEncoder {

    /**
     * Request/Response encoding
     *
     * @var string
     */
    private $encoding;

    /**
     * Response XML header
     *
     * @var string
     */
    private $response_header = '<?xml version="1.0" encoding="__ENCODING__"?><methodResponse />';

    /**
     * Call XML header
     *
     * @var string
     */
    private $call_header = '<?xml version="1.0" encoding="__ENCODING__"?><methodCall />';

    private $special_types = array();

    /**
     * Constructor method
     */
    final public function __construct() {

        $this->encoding = defined("XMLRPC_DEFAULT_ENCODING") ? strtolower(XMLRPC_DEFAULT_ENCODING) : 'utf-8';

    }

    /**
     * Set encoding 
     *
     * @param   sting   $encoding
     * @return  Object  $this 
     */
    final public function setEncoding($encoding) {

        $this->encoding = $encoding; //strtolower($encoding);

        return $this;

    }

    /**
     * Get encoding 
     *
     * @return  string
     */
    final public function getEncoding($encoding) {

        return $this->encoding;

    }

    final public function setValueType(&$value, $type) {

        if ( empty($value) OR !in_array(strtolower($type), array("base64","datetime")) ) throw new XmlrpcException("Invalid value type");

        $this->special_types[$value] = strtolower($type);

        return $this;

    }

    /**
     * Encode an xmlrpc response
     *
     * It expects a scalar, array or NULL as $data and will try to encode it as a valid xmlrpc response.
     *
     * @param   mixed   $data
     *
     * @return  string  xmlrpc formatted response
     *
     * @throws  XmlrpcException | Exception
     */
    public function encodeResponse($data) {

        $xml = new SimpleXMLElement(str_replace('__ENCODING__', $this->encoding, $this->response_header));

        $params = $xml->addChild("params");

        $param = $params->addChild("param");

        $value = $param->addChild("value");

        try {
            
            $this->encodeValue($value, $data);

        } catch (XmlrpcException $xe) {
            
            throw $xe;

        } catch (Exception $e) {
            
            throw $e;

        }

        return $xml->asXML();

    }

    /**
     * Encode an xmlrpc call
     *
     * It expects an array of values as $data and will try to encode it as a valid xmlrpc call.
     *
     * @param   string  $method
     * @param   array   $data
     *
     * @return  string  xmlrpc formatted call
     *
     * @throws  XmlrpcException | Exception
     */
    public function encodeCall($method, $data) {

        $xml = new SimpleXMLElement(str_replace('__ENCODING__', $this->encoding, $this->call_header));

        $xml->addChild("methodName",trim($method));

        $params = $xml->addChild("params");
        
        try {
            
            foreach ($data as $d) {

                $param = $params->addChild("param");

                $value = $param->addChild("value");

                $this->encodeValue($value, $d);

            }

        } catch (XmlrpcException $xe) {
            
            throw $xe;

        }

        return $xml->asXML();

    }

    /**
     * Encode an xmlrpc error
     *
     * @param   int     $error_code
     * @param   string  $error_message
     *
     * @return  string  xmlrpc formatted error
     */
    public function encodeError($error_code, $error_message) {

        $payload  = '<?xml version="1.0" encoding="'.$this->encoding.'"?>' . "\n";
        $payload .= "<methodResponse>\n";
        $payload .= "  <fault>\n";
        $payload .= "    <value>\n";
        $payload .= "      <struct>\n";
        $payload .= "        <member>\n";
        $payload .= "          <name>faultCode</name>\n";
        $payload .= "          <value><int>".$error_code."</int></value>\n";
        $payload .= "        </member>\n";
        $payload .= "        <member>\n";
        $payload .= "          <name>faultString</name>\n";
        $payload .= "          <value><string>".$error_name."</string></value>\n";
        $payload .= "        </member>\n";
        $payload .= "      </struct>\n";
        $payload .= "    </value>\n";
        $payload .= "  </fault>\n";
        $payload .= "</methodResponse>";

        return $payload;

    }

    /**
     * Encode a value into SimpleXMLElement object $xml
     *
     * @param   SimpleXMLElement    $xml
     * @param   string              $value
     *
     * @throws  XmlrpcException
     */
    private function encodeValue(SimpleXMLElement $xml, $value) {

        if ( $value === NULL ) {

            $xml->addChild("nil");

        } else if ( is_array($value) ) {
            
            if ( !$this->catchStruct($value) ) $this->encodeArray($xml, $value);

            else $this->encodeStruct($xml, $value);

        } else if ( @array_key_exists($value, $this->special_types) ) {

            if ( $this->special_types[$value] == "base64" ) $xml->addChild("base64", $value);

            else $xml->addChild("dateTime.iso8601", $this->timestampToIso8601Time($value));

        } else if ( is_bool($value) ) {

            $xml->addChild("boolean", $value ? 1 : 0);

        } else if ( is_double($value) ) {

            $xml->addChild("double", $value);

        } else if ( is_integer($value) ) {
            
            $xml->addChild("int", $value);

        } else if ( is_object($value) ) {

            $this->encodeObject($xml, $value);

        } else if ( is_string($value) ) {

            $xml->addChild("string", htmlspecialchars($value, ENT_XML1, $this->encoding));

        } else throw new XmlrpcException("Unknown type for encoding");
        
    }

    /**
     * Encode an array into SimpleXMLElement object $xml
     *
     * @param   SimpleXMLElement    $xml
     * @param   string              $value
     */
    private function encodeArray(SimpleXMLElement $xml, $value) {
        
        $array = $xml->addChild("array");
        
        $data = $array->addChild("data");
        
        foreach ($value as $entry) {
        
            $val = $data->addChild("value");

            $this->encodeValue($val, $entry);

        }

    }

    /**
     * Encode an object into SimpleXMLElement object $xml
     *
     * @param   SimpleXMLElement    $xml
     * @param   string              $value
     *
     * @throws  XmlrpcException
     */
    private function encodeObject(SimpleXMLElement $xml, $value) {

        if ($value instanceof DataObject) {

            $this->encodeValue($xml, $value->export());

        } else if ($value instanceof DateTime) {

            $xml->addChild("dateTime.iso8601", $this->timestampToIso8601Time($value->format('U')));

        } else throw new XmlrpcException("Unknown type for encoding");
        
    }

    /**
     * Encode a struct into SimpleXMLElement object $xml
     *
     * @param   SimpleXMLElement    $xml
     * @param   string              $value
     *
     * @throws  XmlrpcException
     */
    private function encodeStruct(SimpleXMLElement $xml, $value) {

        $struct = $xml->addChild("struct");

        foreach ($value as $k => $v) {

            $member = $struct->addChild("member");

            $member->addChild("name", $k);

            $val = $member->addChild("value");

            $this->encodeValue($val, $v);

        }

    }

    /**
     * Return true if $value is a struct, false otherwise
     *
     * @param   mixed   $value
     *
     * @return  bool
     */
    private function catchStruct($value) {

        for ( $i = 0; $i < count($value); $i++ ) if ( !array_key_exists($i, $value) ) return true;

        return false;

    }

    /**
     * Convert timestamp to Iso8601
     *
     * @param   int     $timestamp
     *
     * @return  string  Iso8601 formatted date
     */
    private function timestampToIso8601Time($timestamp) {
    
        return date("Ymd\TH:i:s", $timestamp);

    }

}
