<?php

class CRM_Civiquickfile_Base {
  private static $singleton;
  private $_quickfile_account;
  private $_quickfile_apikey;
  private $_quickfile_application;
  protected $_plugin = 'quickfile';

  public function __construct($parameters = array()) {
    $force = FALSE;
    $variables = array(
      'quickfile_account',
      'quickfile_apikey',
      'quickfile_application',
    );
    foreach ($variables as $var) {
      $value = CRM_Utils_Array::value($var, $parameters);
      if(empty($value)) {
        $value = $this->getSetting($var);
      }
      if($value != $this->{'_' .$var}) {
        $force = TRUE;
        $this->{'_' .$var} = $value;
      }
      if(empty($value)) {
        throw new CRM_Core_Exception($var . ts(' has not been set'));
      }
    }
    $this->singleton($this->_quickfile_account, $this->_quickfile_apikey, $this->_quickfile_application, $force);
  }

  /**
   * @param $quickfile_account
   * @param $quickfile_apikey
   * @param $quickfile_application
   * @param bool $force
   *
   * @return CRM_Extension_System
   */
  protected function singleton($quickfile_account, $quickfile_apikey, $quickfile_application, $force = FALSE) {
    if (!self::$singleton || $force) {
      require_once 'packages/Quickfile/Quickfile.php';
      self::$singleton = new Quickfile($quickfile_account, $quickfile_apikey, $quickfile_application);
    }
    return self::$singleton;
  }

  function getSingleton() {
    return self::$singleton;
  }

  /**
  * Get Xero Setting
  * @param String $var
  * @return Ambigous <multitype:, number, unknown>
  */
  function getSetting($var) {
    return civicrm_api3('setting', 'getvalue', array('name' => $var, 'group' => 'Quickfile Settings'));
  }

  /**
   * Convert date to form expected by Xero
   * @param String $date date in mysql format (since it is coming through the api)
   * @return string formatted date
   */
  function formatDateForXero($date) {
    return date("Y-m-d H:m:s", strtotime(CRM_Utils_Date::mysqlToIso($date)));
  }

  /**
   * Validate Response from Quckfile
   *
   * 
   * @param array $response Response From Quickfile
   * @return multitype:string |Ambigous <boolean, multitype:string >
   */
  function validateResponse($response) {
    $message = '';
    $errors  = array();

    //if (is_string($response)) {
    //  foreach ($response as $error_item) {
     //   $keyval   = explode('=', $response_item);
        //$errors[$keyval[0]] = urldecode($keyval[1]);
     // }
      $error_item=$response['Error'];
        if (!empty($error_item)) {
      return $error_item;
    }

//    if (!empty($response['Elements']) && is_array($response['Elements']['DataContractBase']['ValidationErrors'])) {
//      foreach ($response['Elements']['DataContractBase']['ValidationErrors'] as $key => $value) {
//        // we have a situation where the validation errors are an array of errors
//        // original code expected a string - not sure if / when that might happen
//        // this is all a bit of a hackathon @ the moment
//        if (is_array($value[0])) {
//          foreach ($value as $errorMessage) {
//            if (trim($errorMessage['Message']) == 'Account code must be specified') {
//              return array(
//                'You need to set up the account code'
//              );
//            }
//            $message .= " " . $errorMessage['Message'];
//          }
//        }
//        else { // single message - string
//          $message = $value['Message'];
//        }
//        switch (trim($message)) {
//          case "The Contact Name already exists. Please enter a different Contact Name.":
//            $contact = $response['Elements']['DataContractBase']['Contact'];
//            $message .= "<br>contact ID is " . $contact['ContactNumber'];
//            $message .= "<br>contact name is " . $contact['Name'];
//            $message .= "<br>contact email is " . $contact['EmailAddress'];
//            break;
//          case "The TaxType field is mandatory Account code must be specified":
//            $message = "Account code needs setting up";
//        }
//        $errors[] = $message;
//      }
//    }
   return is_array($errors) ? $errors : false;
  }
}
