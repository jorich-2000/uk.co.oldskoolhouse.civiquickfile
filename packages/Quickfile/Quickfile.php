<?php

/**
  From the README file:

  Project Name: PHP Quickfile
  Class Name: Quickfile
  Author: Jonathan Richardson, Oldskoolhouse (dependent on the work of others, mainly David Pitman - see below)
  Date: Jan 2015

  Description:
  A class for interacting with the Quick File (quickfile.co.uk) application API.  More documentation for Quick File can be found at http://api.quickfile.co.uk/

  ---

  License:
  The MIT License

  Copyright (c) 2015 Jonathan Richardson

  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in
  all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
  THE SOFTWARE.



 */
class Quickfile {

    const ENDPOINT = 'http://quickfile.co.uk/WebServices/API/invoices.ashx';

    private $account;
    private $application;
    private $subNo;
    private $apikey;

    public function __construct($account = false, $apikey = false, $application = false, $subNo = false) {
        $this->apikey = $apikey;
        $this->account = $account;
        $this->application = $application;
    

        if (!($this->apikey) || !($this->account) || !($this->application)) {
            error_log('Stuff missing ');
            return false;
        }
    }

    public function __call($name, $arguments) {
        $name = strtolower($name);
        $valid_methods = array(
            'client_search',
            'client_create',
            'client_update',
            'invoice_search',
            'invoice_create'
            );
        
        $methods_map = array(
            'client_search'  => 'Client_Search',
            'client_create'  => 'Client_Create',
            'client_update'  => 'Client_Update',
            'invoice_search' => 'Invoice_Search',
            'invoice_create' => 'Invoice_Create'
        );
   
        //check for valid method first
        if (!in_array($name, $valid_methods)) {
            throw new QuickfileException('The selected method does not exist. Please use one of the following methods: ' . implode(', ', $methods_map));
        }

        $method = $methods_map[$name];
        
        //As SimpleXMLElement always uses "\n" to separate the XML-Declaration from the rest of the document, it can be split at that position and the remainder taken:
        $content = explode("\n", ArrayToXML::toXML($arguments[0],'Body'), 2)[1];
        
        $url = self::ENDPOINT;
        $this->subNo = microtime(true);
        $methodhead = '<' . $method . ' xmlns="http://www.QuickFile.co.uk" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.QuickFile.co.uk http://www.quickfile.co.uk/WebServices/API/Schemas/invoices/' . $method . '.xsd">';
        $header = '<Header>
                        <MessageType>Request</MessageType>
                        <TestMode>true</TestMode>
                        <SubmissionNumber>' . $this->subNo . '</SubmissionNumber>
                        <Authentication>
                            <AccNumber>' . $this->account . '</AccNumber>
                            <MD5Value>' . md5($this->account . $this->apikey . $this->subNo) . '</MD5Value>
                            <ApplicationID>' . $this->application . '</ApplicationID>
                        </Authentication>
                      </Header>';

        $payload = $methodhead . $header . $content . '</' . $method . '>';
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $temp_quickfile_response = curl_exec($ch);

            if ($temp_quickfile_response === false) {
                throw new QuickfileException('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);
        } catch (QuickfileException $e) {
            return $e->getMessage() . "<br/>";
        }

//			if ( isset($where) ) {
//				$quickfile_url .= "?where=$where";
//			}
//			if ( $order ) {
//				$quickfile_url .= "&order=$order";
//			}

        try {
            if (@simplexml_load_string($temp_quickfile_response) == false) {
                throw new QuickfileException($temp_quickfile_response);
                $quickfile_xml = false;
            } else {
                $quickfile_xml = simplexml_load_string($temp_quickfile_response);
            }
        } catch (QuickfileException $e) {
            return $e->getMessage() . "<br/>";
        }

        if (isset($quickfile_xml)) {
            return ArrayToXML::toArray($quickfile_xml);
        }
    }

    
    public function __get($name) {
        return $this->$name();
    }

    public function verify() {
        if (!isset($this->consumer) || !isset($this->token) || !isset($this->signature_method)) {
            return false;
        }
        return true;
    }

}

//END Quickfile class


class ArrayToXML {

    /**
     * The main function for converting to an XML document.
     * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
     *
     * @param array $data
     * @param string $rootNodeName - what you want the root node to be - defaultsto data.
     * @param SimpleXMLElement $xml - should only be used recursively
     * @return string XML
     */
    public static function toXML( $data, $rootNodeName = 'ResultSet', &$xml=null ) {

        // turn off compatibility mode as simple xml throws a wobbly if you don't.
        if ( ini_get('zend.ze1_compatibility_mode') == 1 ) ini_set ( 'zend.ze1_compatibility_mode', 0 );
        if ( is_null( $xml ) ) {
		$xml = simplexml_load_string( "<$rootNodeName />" );
		$rootNodeName = rtrim($rootNodeName, 's');
	}
	// loop through the data passed in.
        foreach( $data as $key => $value ) {

            // no numeric keys in our xml please!
	    $numeric = 0;
            if ( is_numeric( $key ) ) {
                $numeric = 1;
                $key = $rootNodeName;
            }

            // delete any char not allowed in XML element names
            $key = preg_replace('/[^a-z0-9\-\_\.\:]/i', '', $key);

            // if there is another array found recursively call this function
            if ( is_array( $value ) ) {
                $node = ( ArrayToXML::isAssoc( $value ) || $numeric ) ? $xml->addChild( $key ) : $xml;

                // recursive call.
                if ( $numeric ) $key = 'anon';
                ArrayToXML::toXml( $value, $key, $node );
            } else {

                // add single node.
                $value = htmlentities( $value, ENT_NOQUOTES, 'UTF-8', FALSE );
                $xml->addChild( $key, $value );
            }
        }

        // pass back as XML
        return $xml->asXML();

    // if you want the XML to be formatted, use the below instead to return the XML
        //$doc = new DOMDocument('1.0');
        //$doc->preserveWhiteSpace = false;
        //$doc->loadXML( $xml->asXML() );
        //$doc->formatOutput = true;
        //return $doc->saveXML();
    }

    /**
     * Convert an XML document to a multi dimensional array
     * Pass in an XML document (or SimpleXMLElement object) and this recrusively loops through and builds a representative array
     *
     * @param string $xml - XML document - can optionally be a SimpleXMLElement object
     * @return array ARRAY
     */
    public static function toArray($xml) {
        if (is_string($xml))
            $xml = new SimpleXMLElement($xml);
        $children = $xml->children();
        if (!$children)
            return (string) $xml;
        $arr = array();
        foreach ($children as $key => $node) {
            $node = ArrayToXML::toArray($node);

            // support for 'anon' non-associative arrays
            if ($key == 'anon')
                $key = count($arr);

            // if the node is already set, put it into an array
            if (array_key_exists($key, $arr) && isset($arr[$key])) {
                if (!is_array($arr[$key]) || !array_key_exists(0, $arr[$key]) || ( array_key_exists(0, $arr[$key]) && ($arr[$key][0] == null)))
                    $arr[$key] = array($arr[$key]);
                $arr[$key][] = $node;
            } else {
                $arr[$key] = $node;
            }
        }
        return $arr;
    }

    // determine if a variable is an associative array
    public static function isAssoc($array) {
        return (is_array($array) && 0 !== count(array_diff_key($array, array_keys(array_keys($array)))));
    }

}

class QuickfileException extends Exception {
    
}

class QuickfileAPIException extends QuickfileException {

    private $xml;

    public function __construct($xml_exception) {
        $this->xml = $xml_exception;
        $xml = new SimpleXMLElement($xml_exception);

        list($message) = $xml->xpath('/ApiException/Message');
        list($errorNumber) = $xml->xpath('/ApiException/ErrorNumber');
        list($type) = $xml->xpath('/ApiException/Type');

        parent::__construct((string) $type . ': ' . (string) $message, (int) $errorNumber);

        $this->type = (string) $type;
    }

    public function getXML() {
        return $this->xml;
    }

    public static function isException($xml) {
        return preg_match('/^<ApiException.*>/', $xml);
    }

}
