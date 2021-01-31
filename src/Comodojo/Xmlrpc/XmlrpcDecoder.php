<?php

namespace Comodojo\Xmlrpc;

use \Comodojo\Exception\XmlrpcException;
use \SimpleXMLElement;

/** 
 * XML-RPC decoder
 *
 * @package     Comodojo Spare Parts
 * @author      Marco Giovinazzi <marco.giovinazzi@comodojo.org>
 * @license     MIT
 *
 * LICENSE:
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class XmlrpcDecoder
{

    private bool $is_fault = false;

    public function __construct()
    {
        libxml_use_internal_errors(true);
    }

    /**
     * Decode an xmlrpc response
     *
     * @param   string  $response
     * @return  mixed
     *
     * @throws  XmlrpcException
     */
    public function decodeResponse(string $response)
    {
        $xml_data = simplexml_load_string($response);

        if (
            isset($xml_data->fault) &&
            property_exists($xml_data->fault, 'value')
        ) {
            $this->is_fault = true;
            return $this->decodeValue($xml_data->fault->value);
        } else if (
            isset($xml_data->params) &&
            sizeof($xml_data->params) === 1 &&
            property_exists($xml_data->params, 'param')
        ) {
            return $this->decodeValue($xml_data->params->param->value);
        } else {
            throw new XmlrpcException("Not a valid XMLRPC response");
        }
    }

    public function isFault()
    {
        return $this->is_fault;
    }

    /**
     * Decode an xmlrpc request.
     *
     * Can handle single or multicall requests and return an array of: [method], [data]
     *
     * WARNING: in case of multicall, it will not throw any exception for an invalid
     * boxcarred request; a null value will be placed instead of array(method,params).
     *
     * @param   string  $request
     * @return  array
     *
     * @throws  XmlrpcException
     */
    public function decodeCall(string $request): array
    {
        $xml_data = simplexml_load_string($request);

        if ($xml_data === false) {
            throw new XmlrpcException("Not a valid XMLRPC call");
        }

        if (!isset($xml_data->methodName)) {
            throw new XmlrpcException("Uncomprensible request");
        }

        $method_name = $this->decodeString($xml_data->methodName[0]);

        if ($method_name === "system.multicall") {
            return $this->multicallDecode($xml_data);
        } else {
            $parsed = [];
            foreach ($xml_data->params->param as $param) {
                $parsed[] = $this->decodeValue($param->value);
            }
            return [$method_name, $parsed];
        }
    }

    /**
     * Decode an xmlrpc multicall
     *
     * @param   string  $request
     * @return  array
     *
     * @throws  XmlrpcException
     */
    public function decodeMulticall(string $request): array
    {
        $xml_data = simplexml_load_string($request);

        if ($xml_data === false) {
            throw new XmlrpcException("Not a valid XMLRPC multicall");
        }

        if (!isset($xml_data->methodName)) {
            throw new XmlrpcException("Uncomprensible multicall request");
        }

        if ($this->decodeString($xml_data->methodName[0]) != "system.multicall") {
            throw new XmlrpcException("Invalid multicall request");
        }

        return $this->multicallDecode($xml_data);
    }

    /**
     * Decode a value from xmlrpc data
     *
     * @param   mixed   $value
     * @return  mixed
     *
     * @throws  XmlrpcException
     */
    private function decodeValue($value)
    {
        $children = $value->children();

        if (count($children) != 1) {
            throw new XmlrpcException("Cannot decode value: invalid value element");
        }

        $child = $children[0];

        $child_type = $child->getName();

        switch ($child_type) {

            case "i4":
            case "int":
                $return_value = $this->decodeInt($child);
                break;

            case "double":
                $return_value = $this->decodeDouble($child);
                break;

            case "boolean":
                $return_value = $this->decodeBool($child);
                break;

            case "base64":
                $return_value = $this->decodeBase($child);
                break;

            case "dateTime.iso8601":
                $return_value = $this->decodeIso8601Datetime($child);
                break;

            case "string":
                $return_value = $this->decodeString($child);
                break;

            case "array":
                $return_value = $this->decodeArray($child);
                break;

            case "struct":
                $return_value = $this->decodeStruct($child);
                break;

            case "nil":
            case "ex:nil":
                $return_value = $this->decodeNil();
                break;

            default:
                throw new XmlrpcException("Cannot decode value: invalid value type");
                break;
        }

        return $return_value;
    }

    /**
     * Decode an XML-RPC <base64> element
     */
    private function decodeBase($field): string
    {
        return base64_decode($this->decodeString($field));
    }

    /**
     * Decode an XML-RPC <boolean> element
     */
    private function decodeBool($field): bool
    {
        return filter_var($field, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Decode an XML-RPC <dateTime.iso8601> element
     */
    private function decodeIso8601Datetime($field): int
    {
        return strtotime($field);
    }

    /**
     * Decode an XML-RPC <double> element
     */
    private function decodeDouble($field): float
    {
        return (float) ($this->decodeString($field));
    }

    /**
     * Decode an XML-RPC <int> or <i4> element
     */
    private function decodeInt($field): int
    {
        return filter_var($field, FILTER_VALIDATE_INT);
    }

    /**
     * Decode an XML-RPC <string>
     */
    private function decodeString($field): string
    {
        return (string) $field;
    }

    /**
     * Decode an XML-RPC <nil/>
     */
    private function decodeNil()
    {
        return null;
    }

    /**
     * Decode an XML-RPC <struct>
     */
    private function decodeStruct($field): array
    {
        $return_value = [];

        foreach ($field->member as $member) {

            $name = (string) $member->name;
            $value = $this->decodeValue($member->value);
            $return_value[$name] = $value;
        }

        return $return_value;
    }

    /** 
     * Decode an XML-RPC <array> element
     */
    private function decodeArray($field): array
    {
        $return_value = [];

        foreach ($field->data->value as $value) {

            $return_value[] = $this->decodeValue($value);
        }

        return $return_value;
    }

    /** 
     * Decode an XML-RPC multicall request (internal)
     * 
     * @param SimpleXMLElement $xml_data
     */
    private function multicallDecode(SimpleXMLElement $xml_data): array
    {
        $data = [];

        $calls = $xml_data->params->param->value->children();

        $calls_array = $this->decodeArray($calls[0]);
        foreach ($calls_array as $call) {
            $data[] = (!isset($call['methodName']) || !isset($call['params']))
                ? null : [$call['methodName'], $call['params']];
        }

        return $data;
    }
}
