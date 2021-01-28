<?php

namespace Comodojo\Xmlrpc;

use \Comodojo\Exception\XmlrpcException;
use \XMLWriter;
use \Exception;
use \DateTime;

/** 
 * XML-RPC encoder
 *
 * Main features:
 * - support for <nil /> and <ex:nil />
 * - implements true, XML compliant, HTML numeric entities conversion
 * - support for CDATA values
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

class XmlrpcEncoder
{

    /**
     * Special data types currently supported
     *
     * @var array
     */
    private const SPECIAL_TYPES = ["base64", "datetime", "cdata"];

    /**
     * Request/Response encoding
     *
     * @var string
     */
    private string $encoding = 'utf-8';

    /**
     * References of special values (base64, datetime, cdata)
     *
     * @var array
     */
    private array $special_types = [];

    /**
     * ex:nil switch (for apache rpc compatibility)
     *
     * @var bool
     */
    private bool $use_ex_nil = false;

    /**
     * Constructor method
     * 
     * @param string $encoding
     */
    public function __construct(string $encoding = null)
    {
        $this->setEncoding($encoding);
    }

    /**
     * Set encoding 
     *
     * @param   string   $encoding
     * @return  XmlrpcEncoder
     */
    final public function setEncoding(string $encoding = null): XmlrpcEncoder
    {
        if (!empty($encoding)) {
            $this->encoding = strtolower($encoding);
        }

        return $this;
    }

    /**
     * Get encoding 
     *
     * @return  string
     */
    final public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * Handle base64 and datetime values 
     *
     * @param   mixed    $value  The referenced value
     * @param   string   $type   The type of value
     * @return  XmlrpcEncoder
     */
    final public function setValueType(&$value, string $type): XmlrpcEncoder
    {
        $type = strtolower($type);

        if (
            empty($value) ||
            !in_array($type, self::SPECIAL_TYPES)
        ) {
            throw new XmlrpcException("Invalid value type");
        }

        $this->special_types[$value] = $type;

        return $this;
    }

    /**
     * Use <ex:nil /> instead of <nil /> (apache xmlrpc compliant)
     *
     * @param   bool    $mode
     *
     * @return  XmlrpcEncoder 
     */
    final public function useExNil(bool $mode = true): XmlrpcEncoder
    {
        $this->use_ex_nil = filter_var($mode, FILTER_VALIDATE_BOOLEAN);
        return $this;
    }

    /**
     * Encode an xmlrpc response
     *
     * It expects a scalar, array or NULL as $data and will try to encode it as a valid xmlrpc response.
     *
     * @param   mixed   $data
     * @return  string  xmlrpc formatted response
     *
     * @throws  XmlrpcException
     * @throws  Exception
     */
    public function encodeResponse($data): string
    {
        $xml = new XMLWriter();

        $xml->openMemory();
        $xml->setIndent(false);
        $xml->startDocument('1.0', $this->encoding);
        $xml->startElement("methodResponse");
        $xml->startElement("params");
        $xml->startElement("param");
        $xml->startElement("value");

        $this->encodeValue($xml, $data);

        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return trim($xml->outputMemory());
    }

    /**
     * Encode an xmlrpc call
     *
     * It expects an array of values as $data and will try to encode it as a valid xmlrpc call.
     *
     * @param   string  $method
     * @param   array   $data
     * @return  string  xmlrpc formatted call
     *
     * @throws  XmlrpcException
     * @throws  Exception
     */
    public function encodeCall(string $method, array $data = []): string
    {
        $xml = new XMLWriter();

        $xml->openMemory();
        $xml->setIndent(false);
        $xml->startDocument('1.0', $this->encoding);
        $xml->startElement("methodCall");
        $xml->writeElement("methodName", trim($method));
        $xml->startElement("params");

        foreach ($data as $d) {
            $xml->startElement("param");
            $xml->startElement("value");
            $this->encodeValue($xml, $d);
            $xml->endElement();
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return trim($xml->outputMemory());
    }

    /**
     * Encode an xmlrpc multicall
     *
     * It expects in input a key->val array where key
     * represent the method and val the parameters.
     *
     * @param   array   $data
     * @return  string  xmlrpc formatted call
     *
     * @throws  XmlrpcException
     * @throws  Exception
     */
    public function encodeMulticall(array $data): string
    {
        $packed_requests = [];

        foreach ($data as $methodName => $params) {
            if (is_int($methodName) && count($params) == 2) {
                array_push($packed_requests, [
                    "methodName" =>  $params[0],
                    "params"     =>  $params[1]
                ]);
            } else {
                array_push($packed_requests, [
                    "methodName" =>  $methodName,
                    "params"     =>  $params
                ]);
            }
        }

        return $this->encodeCall("system.multicall", [$packed_requests]);
    }

    /**
     * Encode an xmlrpc error
     *
     * @param   int     $error_code
     * @param   string  $error_message
     * @return  string  xmlrpc formatted error
     */
    public function encodeError(int $error_code, string $error_message): string
    {
        return '<?xml version="1.0" encoding="' . $this->encoding . '"?>' .
            "<methodResponse>" .
            $this->encodeFault($error_code, $error_message) .
            "</methodResponse>";
    }

    /**
     * Encode an xmlrpc fault (without full xml document body)
     *
     * @param   int     $error_code
     * @param   string  $error_message
     * @return  string  xmlrpc formatted error
     */
    private function encodeFault(int $error_code, string $error_message): string
    {
        $value = htmlentities(
            $error_message,
            ENT_QUOTES,
            $this->encoding,
            false
        );

        $string = preg_replace_callback('/&([a-zA-Z][a-zA-Z0-9]+);/S', 'self::numericEntities', $value);

        return "<fault>" .
            "<value>" .
            "<struct>" .
            "<member>" .
            "<name>faultCode</name>" .
            "<value><int>$error_code</int></value>" .
            "</member>" .
            "<member>" .
            "<name>faultString</name>" .
            "<value><string>$string</string></value>" .
            "</member>" .
            "</struct>" .
            "</value>" .
            "</fault>";
    }

    /**
     * Encode a value using XMLWriter object $xml
     *
     * @param   XMLWriter    $xml
     * @param   mixed        $value
     *
     * @throws  XmlrpcException
     */
    private function encodeValue(XMLWriter $xml, $value): void
    {
        if ($value === null) {

            $xml->writeRaw($this->use_ex_nil === true ? '<ex:nil />' : '<nil />');
        } else if (is_array($value)) {

            if (!self::catchStruct($value)) {
                $this->encodeArray($xml, $value);
            } else {
                $this->encodeStruct($xml, $value);
            }
        } else if (@array_key_exists($value, $this->special_types)) {

            switch ($this->special_types[$value]) {
                case 'base64':
                    $xml->writeElement("base64", $value);
                    break;
                case 'datetime':
                    $xml->writeElement("dateTime.iso8601", self::timestampToIso8601Time($value));
                    break;
                case 'cdata':
                    $xml->writeCData($value);
                    break;
            }
        } else if (is_bool($value)) {
            $xml->writeElement("boolean", $value ? 1 : 0);
        } else if (is_double($value)) {
            $xml->writeElement("double", $value);
        } else if (is_integer($value)) {
            $xml->writeElement("int", $value);
        } else if (is_object($value)) {
            $this->encodeObject($xml, $value);
        } else if (is_string($value)) {

            $value = htmlentities($value, ENT_QUOTES, $this->encoding, false);
            $string = preg_replace_callback('/&([a-zA-Z][a-zA-Z0-9]+);/S', 'self::numericEntities', $value);
            $xml->writeRaw("<string>$string</string>");
        } else {
            throw new XmlrpcException("Unknown type for encoding");
        }
    }

    /**
     * Encode an array using XMLWriter object $xml
     *
     * @param   XMLWriter    $xml
     * @param   mixed        $value
     */
    private function encodeArray(XMLWriter $xml, $value): void
    {
        $xml->startElement("array");
        $xml->startElement("data");

        foreach ($value as $entry) {
            $xml->startElement("value");
            $this->encodeValue($xml, $entry);
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();
    }

    /**
     * Encode an object using XMLWriter object $xml
     *
     * @param   XMLWriter    $xml
     * @param   mixed        $value
     *
     * @throws  XmlrpcException
     */
    private function encodeObject(XMLWriter $xml, $value): void
    {
        if ($value instanceof DateTime) {
            $xml->writeElement("dateTime.iso8601", self::timestampToIso8601Time($value->format('U')));
        } else {
            throw new XmlrpcException("Unknown object type to be encoded");
        }
    }

    /**
     * Encode a struct using XMLWriter object $xml
     *
     * @param   XMLWriter    $xml
     * @param   mixed        $value
     *
     * @throws  XmlrpcException
     */
    private function encodeStruct(XMLWriter $xml, $value): void
    {
        $xml->startElement("struct");

        foreach ($value as $k => $v) {
            $xml->startElement("member");
            $xml->writeElement("name", $k);
            $xml->startElement("value");
            $this->encodeValue($xml, $v);
            $xml->endElement();
            $xml->endElement();
        }

        $xml->endElement();
    }

    /**
     * Return true if $value is a struct, false otherwise
     *
     * @param   mixed   $value
     * @return  bool
     */
    private static function catchStruct($value): bool
    {
        $values = count($value);
        for ($i = 0; $i < $values; $i++) {
            if (!array_key_exists($i, $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert timestamp to Iso8601
     *
     * @param   int     $timestamp
     * @return  string  Iso8601 formatted date
     */
    private static function timestampToIso8601Time(int $timestamp): string
    {
        return date("Ymd\TH:i:s", $timestamp);
    }

    /**
     * Recode named entities into numeric ones
     *
     * @param   array   matches
     * @return  string 
     */
    private static function numericEntities(array $matches): string
    {
        static $table = [
            'quot' => '&#34;', 'amp' => '&#38;', 'lt' => '&#60;', 'gt' => '&#62;', 'OElig' => '&#338;', 'oelig' => '&#339;',
            'Scaron' => '&#352;', 'scaron' => '&#353;', 'Yuml' => '&#376;', 'circ' => '&#710;', 'tilde' => '&#732;', 'ensp' => '&#8194;',
            'emsp' => '&#8195;', 'thinsp' => '&#8201;', 'zwnj' => '&#8204;', 'zwj' => '&#8205;', 'lrm' => '&#8206;', 'rlm' => '&#8207;',
            'ndash' => '&#8211;', 'mdash' => '&#8212;', 'lsquo' => '&#8216;', 'rsquo' => '&#8217;', 'sbquo' => '&#8218;', 'ldquo' => '&#8220;',
            'rdquo' => '&#8221;', 'bdquo' => '&#8222;', 'dagger' => '&#8224;', 'Dagger' => '&#8225;', 'permil' => '&#8240;', 'lsaquo' => '&#8249;',
            'rsaquo' => '&#8250;', 'euro' => '&#8364;', 'fnof' => '&#402;', 'Alpha' => '&#913;', 'Beta' => '&#914;', 'Gamma' => '&#915;',
            'Delta' => '&#916;', 'Epsilon' => '&#917;', 'Zeta' => '&#918;', 'Eta' => '&#919;', 'Theta' => '&#920;', 'Iota' => '&#921;',
            'Kappa' => '&#922;', 'Lambda' => '&#923;', 'Mu' => '&#924;', 'Nu' => '&#925;', 'Xi' => '&#926;', 'Omicron' => '&#927;',
            'Pi' => '&#928;', 'Rho' => '&#929;', 'Sigma' => '&#931;', 'Tau' => '&#932;', 'Upsilon' => '&#933;', 'Phi' => '&#934;',
            'Chi' => '&#935;', 'Psi' => '&#936;', 'Omega' => '&#937;', 'alpha' => '&#945;', 'beta' => '&#946;', 'gamma' => '&#947;',
            'delta' => '&#948;', 'epsilon' => '&#949;', 'zeta' => '&#950;', 'eta' => '&#951;', 'theta' => '&#952;', 'iota' => '&#953;',
            'kappa' => '&#954;', 'lambda' => '&#955;', 'mu' => '&#956;', 'nu' => '&#957;', 'xi' => '&#958;', 'omicron' => '&#959;',
            'pi' => '&#960;', 'rho' => '&#961;', 'sigmaf' => '&#962;', 'sigma' => '&#963;', 'tau' => '&#964;', 'upsilon' => '&#965;',
            'phi' => '&#966;', 'chi' => '&#967;', 'psi' => '&#968;', 'omega' => '&#969;', 'thetasym' => '&#977;', 'upsih' => '&#978;',
            'piv' => '&#982;', 'bull' => '&#8226;', 'hellip' => '&#8230;', 'prime' => '&#8242;', 'Prime' => '&#8243;', 'oline' => '&#8254;',
            'frasl' => '&#8260;', 'weierp' => '&#8472;', 'image' => '&#8465;', 'real' => '&#8476;', 'trade' => '&#8482;', 'alefsym' => '&#8501;',
            'larr' => '&#8592;', 'uarr' => '&#8593;', 'rarr' => '&#8594;', 'darr' => '&#8595;', 'harr' => '&#8596;', 'crarr' => '&#8629;',
            'lArr' => '&#8656;', 'uArr' => '&#8657;', 'rArr' => '&#8658;', 'dArr' => '&#8659;', 'hArr' => '&#8660;', 'forall' => '&#8704;',
            'part' => '&#8706;', 'exist' => '&#8707;', 'empty' => '&#8709;', 'nabla' => '&#8711;', 'isin' => '&#8712;', 'notin' => '&#8713;',
            'ni' => '&#8715;', 'prod' => '&#8719;', 'sum' => '&#8721;', 'minus' => '&#8722;', 'lowast' => '&#8727;', 'radic' => '&#8730;',
            'prop' => '&#8733;', 'infin' => '&#8734;', 'ang' => '&#8736;', 'and' => '&#8743;', 'or' => '&#8744;', 'cap' => '&#8745;',
            'cup' => '&#8746;', 'int' => '&#8747;', 'there4' => '&#8756;', 'sim' => '&#8764;', 'cong' => '&#8773;', 'asymp' => '&#8776;',
            'ne' => '&#8800;', 'equiv' => '&#8801;', 'le' => '&#8804;', 'ge' => '&#8805;', 'sub' => '&#8834;', 'sup' => '&#8835;',
            'nsub' => '&#8836;', 'sube' => '&#8838;', 'supe' => '&#8839;', 'oplus' => '&#8853;', 'otimes' => '&#8855;', 'perp' => '&#8869;',
            'sdot' => '&#8901;', 'lceil' => '&#8968;', 'rceil' => '&#8969;', 'lfloor' => '&#8970;', 'rfloor' => '&#8971;', 'lang' => '&#9001;',
            'rang' => '&#9002;', 'loz' => '&#9674;', 'spades' => '&#9824;', 'clubs' => '&#9827;', 'hearts' => '&#9829;', 'diams' => '&#9830;',
            'nbsp' => '&#160;', 'iexcl' => '&#161;', 'cent' => '&#162;', 'pound' => '&#163;', 'curren' => '&#164;', 'yen' => '&#165;',
            'brvbar' => '&#166;', 'sect' => '&#167;', 'uml' => '&#168;', 'copy' => '&#169;', 'ordf' => '&#170;', 'laquo' => '&#171;',
            'not' => '&#172;', 'shy' => '&#173;', 'reg' => '&#174;', 'macr' => '&#175;', 'deg' => '&#176;', 'plusmn' => '&#177;',
            'sup2' => '&#178;', 'sup3' => '&#179;', 'acute' => '&#180;', 'micro' => '&#181;', 'para' => '&#182;', 'middot' => '&#183;',
            'cedil' => '&#184;', 'sup1' => '&#185;', 'ordm' => '&#186;', 'raquo' => '&#187;', 'frac14' => '&#188;', 'frac12' => '&#189;',
            'frac34' => '&#190;', 'iquest' => '&#191;', 'Agrave' => '&#192;', 'Aacute' => '&#193;', 'Acirc' => '&#194;', 'Atilde' => '&#195;',
            'Auml' => '&#196;', 'Aring' => '&#197;', 'AElig' => '&#198;', 'Ccedil' => '&#199;', 'Egrave' => '&#200;', 'Eacute' => '&#201;',
            'Ecirc' => '&#202;', 'Euml' => '&#203;', 'Igrave' => '&#204;', 'Iacute' => '&#205;', 'Icirc' => '&#206;', 'Iuml' => '&#207;',
            'ETH' => '&#208;', 'Ntilde' => '&#209;', 'Ograve' => '&#210;', 'Oacute' => '&#211;', 'Ocirc' => '&#212;', 'Otilde' => '&#213;',
            'Ouml' => '&#214;', 'times' => '&#215;', 'Oslash' => '&#216;', 'Ugrave' => '&#217;', 'Uacute' => '&#218;', 'Ucirc' => '&#219;',
            'Uuml' => '&#220;', 'Yacute' => '&#221;', 'THORN' => '&#222;', 'szlig' => '&#223;', 'agrave' => '&#224;', 'aacute' => '&#225;',
            'acirc' => '&#226;', 'atilde' => '&#227;', 'auml' => '&#228;', 'aring' => '&#229;', 'aelig' => '&#230;', 'ccedil' => '&#231;',
            'egrave' => '&#232;', 'eacute' => '&#233;', 'ecirc' => '&#234;', 'euml' => '&#235;', 'igrave' => '&#236;', 'iacute' => '&#237;',
            'icirc' => '&#238;', 'iuml' => '&#239;', 'eth' => '&#240;', 'ntilde' => '&#241;', 'ograve' => '&#242;', 'oacute' => '&#243;',
            'ocirc' => '&#244;', 'otilde' => '&#245;', 'ouml' => '&#246;', 'divide' => '&#247;', 'oslash' => '&#248;', 'ugrave' => '&#249;',
            'uacute' => '&#250;', 'ucirc' => '&#251;', 'uuml' => '&#252;', 'yacute' => '&#253;', 'thorn' => '&#254;', 'yuml' => '&#255;'
        ];

        // cleanup invalid entities
        return $table[$matches[1]] ?? '';
    }
}
