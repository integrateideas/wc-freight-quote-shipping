<?php

class FreightQuote 
{
    protected $_gatewayUrl = 'https://b2b.Freightquote.com/WebService/QuoteService.asmx';
    protected $_response = null;
    protected $_url = 'http://tempuri.org/';
    protected $_username = 'xmltest@freightquote.com';
    protected $_password = 'XML';

    function __construct($settings) {

        if(isset($settings['username'])&&isset($settings['password'])){
          $this->_username = $settings['username'] ;
          $this->_password = $settings['password'] ;
        }
        
    }

    public function getQuotes($body){

      $required = array(
          'CustomerId',
          'QuoteType',
          'ServiceType',
          'QuoteShipment' => array(
            'IsBlind',
            'ShipmentLocations' => array(
              'Location' => array(
                'LocationType',
                'LocationAddress'=> array(
                  'PostalCode',
                  'CountryCode',
                ),
              ),
            ),
            'ShipmentProducts' => array(
              'Product' => array(
                'ProductDescription',
                'PackageType',
                'ContentType',
                'IsHazardousMaterial',
                'PieceCount',
                'Height',
                'Length',
                'Width',
                'Weight'
              )
            ),
          ),
      );

      if(!$body){
        return false;
      }
      $res = $this->_validateFields($body,$required);

      if(isset($res['status'])&&!$res['status']){
        return $res;
      }

      $requestXml = array(
        'GetRatingEngineQuote' => array(
          'request' => $body,
          'user' => array(
            'Name' => $this->_username,
            'Password' => $this->_password
          )
        )
      );
      unset($body);
      $response = $this->_executeRequest($requestXml,'GetRatingEngineQuote');

      if (!$response || !isset($response['GetRatingEngineQuoteResponse'])) {
          return false;
      }

      $response = $response['GetRatingEngineQuoteResponse'][0]['GetRatingEngineQuoteResult'][0];

      if (isset($response['ValidationErrors']) && count($response['ValidationErrors']) > 0) {
        return ['error' => $response['ValidationErrors'][0]['B2BError'][0],'status'=>0];
      }

      return ['data'=>$response,'status'=>1];
    }

    public function requestPickups($body){

      $required = array(
          'CustomerId',
          'QuoteId',
          'OptionId',
          'QuoteShipment' => array(
            'IsBlind',
            'PickupDate',
            'ShipmentLocations' => array(
              'Location' => array(
                  'LocationType',
                  'ContactName',
                  'ContactPhone',
                  'ContactEmail',
                  'LocationAddress' => array(
                    'AddressName',
                    'StreetAddress',
                    'City',
                    'StateCode',
                    'PostalCode',
                    'CountryCode'
                  ),
              ),
            ),
            'ShipmentProducts' => array(
              'Product' => array(
                'ProductDescription',
                'PackageType',
                'ContentType',
                'IsHazardousMaterial',
                'PieceCount',
                'Height',
                'Length',
                'Width',
                'Weight'
              ),
            ),
          ),
      );
      
      if(!$body){
        return false;
      }
      
      $res = $this->_validateFields($body,$required);

      if(isset($res['status'])&&!$res['status']){
        return $res;
      }

      $requestXml = array(
          'RequestShipmentPickup' => array(
              'request' => $body,
              'user' => array(
                'Name' => $this->_username,
                'Password' => $this->_password
              )
          )
      );
      unset($body);
      
      $response = $this->_executeRequest($requestXml,'RequestShipmentPickup');

      if (!$response || !isset($response['RequestShipmentPickupResponse'])) {
          return false;
      }

      $response = $response['RequestShipmentPickupResponse'][0]['RequestShipmentPickupResult'][0];

      if (isset($response['ValidationErrors']) && count($response['ValidationErrors']) > 0) {
        return $response['ValidationErrors'];
      }

      return $response;

    }

    public function getTrackingDetails($bolNumber){
      
      if(!$bolNumber){
        return false;
      }
      $requestXml = array(
            'GetTrackingInformation' => array(
                'request' => array(
                  'BOLNumber' => $bolNumber
                )
            )
      );

      $response = $this->_executeRequest($requestXml,'GetTrackingInformation');

      if (!$response || !isset($response['GetTrackingInformationResponse'])) {
          return false;
      }

      $response = $response['GetTrackingInformationResponse'][0]['GetTrackingInformationResult'][0];

      if (isset($response['ValidationErrors']) && count($response['ValidationErrors']) > 0) {
        return $response['ValidationErrors'];
      }

      return $response;

    }

    public function cancelShipment($bolNumber,$quoteId){
      
      if(!$bolNumber && !$quoteId){
        return false;
      }
      $requestXml = array(
            'RequestShipmentCancellation' => array(
                'request' => array(
                  'BOLNumber' => $bolNumber,
                  'QuoteId' => $quoteId,
                ),
                'user' => array(
                  'Name' => $this->_username,
                  'Password' => $this->_password
                )
            )
      );

      $response = $this->_executeRequest($requestXml,'RequestShipmentCancellation');

      if (!$response || !isset($response['RequestShipmentCancellationResponse'])) {
          return false;
      }

      $response = $response['RequestShipmentCancellationResponse'][0]['RequestShipmentCancellationResult'][0];

      if (isset($response['ValidationErrors']) && count($response['ValidationErrors']) > 0) {
        return $response['ValidationErrors'];
      }

      return $response;

    }

    private function _validateFields($data,$keys,$res = true){

      if(ctype_digit(implode('',array_keys($data)))){
  
        foreach ($data as $value) {
          $res = $this->_validateFields($value,$keys,$res);
        }

      }else{

        foreach ($keys as $key => $value ) {

          if(is_array($value)){
            $fieldName = $key;
          }else{
            $fieldName = $value;
          }
    
          if(!isset($data[$fieldName]) || empty($data[$fieldName])) {
            // print_r($fieldName);
             return ['status'=> false ,'error'=>$fieldName.' is missing in FreightQuote.'];
          }
          if(is_array($data[$fieldName]) && is_array($value)){

              $res = $this->_validateFields($data[$fieldName],$value,$res);
          }
        }
      }
      if(isset($res['status'])&&!$res['status']){
        return $res;
      }
      return true;
      
    }

    protected function _executeRequest($arr,$requestType) {

      if(!$requestType){
        // pr('Request type not found on the server.');
        return false;
      }
      //Make sure cURL exists
      if (!function_exists('curl_init')) {
        // pr('Freightquote.com: cURL not found on the server.');
        return false;
      }
      
      //XML template file used for request
      $xml = $this->_arrayToXml($arr);
    
      //Initialize curl
      $ch = curl_init();
      
      $headers = array(
        'Content-Type: text/xml; charset=utf-8',
        'Content-Length: ' . strlen($xml),
        'SOAPAction: "'.$this->_url.$requestType.'"'
      );
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_HEADER, 0); 
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_TIMEOUT, 180);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_URL, $this->_gatewayUrl);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $xml); 
      
      $this->_response = curl_exec($ch);
      if (curl_errno($ch) == 0) {
        curl_close($ch);
        
        //Simple check to make sure that this is a valid XML response
        if (strpos(strtolower($this->_response), 'soap:envelope') === false) {
          pr('Freightquote.com: Invalid response from server.');
          return false;
        }
        if ($this->_response) {
          //Convert the XML into an easy-to-use associative array
          $this->_response = $this->_parseXml($this->_response);       
        }
    
        return $this->_response;
      } else {
        //Collect the error returned
        $curlErrors = curl_error($ch) . ' (Error No. ' . curl_errno($ch) . ')';
        curl_close($ch);
        
        // pr('Freightquote.com: ' . $curlErrors);
        return false;
      }
    }

    protected function _arrayToXml($array, $wrapper = true) {
      $xml = '';
      
      if ($wrapper) {
        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
                 '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n" .
                 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' . "\n" .
               '<soap:Body>' . "\n";
      }
      
      $first_key = true;
      
      foreach ($array as $key => $value) {
        $position = 0;
        
        if (is_array($value)) {
          $is_value_assoc = $this->_isAssoc($value);
          $xml .= "<$key" . ($first_key && $wrapper ? ' xmlns="http://tempuri.org/"' : '') . ">\n";
          $first_key = false;
          
          foreach ($value as $key2 => $value2) {
            if (is_array($value2)) {
              if ($is_value_assoc) {
                $xml .= "<$key2>\n" . $this->_arrayToXml($value2, false) . "</$key2>\n";
              } elseif (is_array($value2)) {
                $xml .= $this->_arrayToXml($value2, false);
                $position++;
                
                if ($position < count($value) && count($value) > 1) $xml .= "</$key>\n<$key>\n";
              }
            } else {
              $xml .= "<$key2>" . $this->_xmlSafe($value2) . "</$key2>\n";
            }
          }
          $xml .= "</$key>\n";
        } else {
        
          $xml .= "<$key>" . $this->_xmlSafe($value) . "</$key>\n";
        }
      }
      
      if ($wrapper) {
        $xml .= '</soap:Body>' . "\n" .
              '</soap:Envelope>';
      }
      
      return $xml;
    }

    protected function _parseXml($text) {
      $reg_exp = '/<(\w+)[^>]*>(.*?)<\/\\1>/s';
      preg_match_all($reg_exp, $text, $match);
      foreach ($match[1] as $key=>$val) {
        if ( preg_match($reg_exp, $match[2][$key]) ) {
            $array[$val][] = $this->_parseXml($match[2][$key]);
        } else {
            $array[$val] = $match[2][$key];
        }
      }
      return $array;
    }

    protected function _xmlSafe($str) {
      //The 5 evil characters in XML
      $str = str_replace('<', '&lt;', $str);
      $str = str_replace('>', '&gt;', $str);
      $str = str_replace('&', '&amp;', $str);
      $str = str_replace("'", '&apos;', $str);
      $str = str_replace('"', '&quot;', $str);
      return $str;
    }

    protected function _isAssoc($array) {
      return (is_array($array) && 0 !== count(array_diff_key($array, array_keys(array_keys($array)))));
    }

}
