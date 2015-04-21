<?php

class CRM_Civiquickfile_Contact extends CRM_Civiquickfile_Base {

  /**
   * pull contacts from Quickfile and store them into civicrm_account_contact
   * We call the civicrm_accountPullPreSave hook so other modules can alter if required
   *
   * @param array $params
   *
   * @throws API_Exception
   * @throws CRM_Core_Exception
   */
  function pull($params) {
      $accountsSearch = $this->mapToAccounts(false,false,true);  //pass blank parameters and flsg to create the search xml
      $result = $this->getSingleton()->Client_Search($accountsSearch);
    if(!is_array($result)){
      throw new API_Exception('Sync Failed', 'quickfile_retrieve_failure', (array) $result);
    }
    if (!empty($result['Body'])){
        $RecordsetCount = $result['Body']['RecordsetCount'];
        $ReturnCount = $result['Body']['ReturnCount'];
      $contacts = $result['Body']['Record'];

      foreach($contacts as $contact){

        $save = TRUE;
        $params = array(
          'accounts_display_name' => $contact['CompanyName'],
          'contact_id' => CRM_Utils_Array::value('ClientID', $contact),
          'accounts_modified_date' => $contact['UpdatedDateUTC'],
          'plugin' => 'quickfile',
          'accounts_contact_id' => $contact['ClientID'],
          'accounts_data' => json_encode($contact),
        );
        CRM_Accountsync_Hook::accountPullPreSave('contact', $contact, $save, $params);
        if(!$save) {
          continue;
        }
        try {
          $params['id'] = civicrm_api3('account_contact', 'getvalue', array(
            'return' => 'id',
            'accounts_contact_id' => $contact['ContactID'],
            'plugin' => $this->_plugin,
          ));
        }
        catch (CiviCRM_API3_Exception $e) {
          // this is an update - but lets just check the contact id doesn't exist in the account_contact table first
          // e.g if a list has been generated but not yet pushed
          try {
            $existing = civicrm_api3('account_contact', 'getsingle', array(
              'return' => 'id',
              'contact_id' => $contact['ContactNumber'],
              'plugin' => $this->_plugin,
            ));
            if(!empty($existing['accounts_contact_id']) && $existing['accounts_contact_id'] != $contact['ContactID']) {
              // no idea how this happened or what it means - calling function can catch & deal with it
              throw new CRM_Core_Exception(ts('Cannot update contact'), 'data_error', $contact);
            }
          }
          catch (CiviCRM_API3_Exception $e) {
            // ok - it IS an update
          }
        }
        try {
          civicrm_api3('account_contact', 'create', $params);
        }
        catch (CiviCRM_API3_Exception $e) {
          CRM_Core_Session::setStatus(ts('Failed to store ') . $params['accounts_display_name']
          . ts(' with error ') . $e->getMessage()
          , ts('Contact Pull failed'));
        }
      }
    }
  }

  /**
   * push contacts to Quickfile from the civicrm_account_contact with 'needs_update' = 1
   * We call the civicrm_accountPullPreSave hook so other modules can alter if required
   *
   * @param array $params
   *  - start_date
   *
   * @return bool
   * @throws CRM_Core_Exception
   * @throws CiviCRM_API3_Exception
   */
  function push($params) {
    $records = civicrm_api3('account_contact', 'get', array(
      'accounts_needs_update' => 1,
      'api.contact.get' => 1,
      'plugin' => $this->_plugin,
      )
    );
    $errors = array();

    foreach ($records['values'] as $record) {
      try {
        
        //@todo: get the primary contact if its an 'organisation'(list of types to come from settings in case peopele change default types)
        //$civiCRMRelationships = $record('api.relationship.get');
        //search for the account
        $accountsSearch = $this->mapToAccounts($record['api.contact.get']['values'][0],$record['contact_id'],true);
        $result = $this->getSingleton()->Client_Search($accountsSearch);
        $responseErrors = $this->validateResponse($result);
                //if error throw error
        
        //if search returns a value
        if ($result["Body"]["ReturnCount"]>0) {
          $record['accounts_contact_id']=$result["Body"]["Record"]["ClientID"];
          $accountsContact = $this->mapToAccounts($record['api.contact.get']['values'][0],$record['contact_id']);
          //for an update we cant pass any empty fields so remove them
          $accountsContact["ClientDetails"]=array_filter($accountsContact["ClientDetails"]);
          $result = $this->getSingleton()->Client_Update($accountsContact);          
        } else
        {
           $accountsContact = $this->mapToAccounts($record['api.contact.get']['values'][0],$record['contact_id']);
           
                unset($accountsContact["ClientDetails"]["ClientID"]);
            
           $result = $this->getSingleton()->Client_Create($accountsContact); 
        }
        $responseErrors = $this->validateResponse($result);
        if($responseErrors) {
          $record['error_data'] = json_encode($responseErrors);
        }
        else {
        //refresh the search for the account as $result does not contain the client data when doing an update
            
        $accountsSearch = $this->mapToAccounts($record['api.contact.get']['values'][0],$record[contact_id],true);
        $result = $this->getSingleton()->Client_Search($accountsSearch);
        $responseErrors = $this->validateResponse($result);
        //throw errors ?
        
          $record['error_data'] = 'null';
          if(empty($record['accounts_contact_id'])) {
            $record['accounts_contact_id'] = $result['Body']['Record']['ClientID'];
          }
          $record['accounts_modified_date'] = date("Y-m-d H:i:s",$result['Header']['SubmissionNumber']);
          $record['accounts_data'] = json_encode($this->mapToCiviCRM($result['Body']['Record']));
          $record['accounts_display_name'] = $result['Body']['Record']['CompanyName'];
        }
        //this will update the last sync date
        $record['accounts_needs_update'] = 0;
        unset($record['last_sync_date']);
        civicrm_api3('account_contact', 'create', $record);
      }
      catch (CiviCRM_API3_Exception $e) {
        $errors[] = ts('Failed to push ') . $record['contact_id'] . ' (' . $record['accounts_contact_id'] . ' )'
          . ts(' with error ') . $e->getMessage() . print_r($responseErrors, TRUE)
          . ts('Contact Push failed');
      }
    }
    if($errors) {
      // since we expect this to wind up in the job log we'll print the errors
      throw new CRM_Core_Exception(ts('Not all contacts were saved') . print_r($errors, TRUE), 'incomplete', $errors);
    } 
    return TRUE; 
  }

  /**
   * mapToCiviCRM
   * 
   * Map accounts Array to civicrm  field names
   *
   * @param array $contact Contact Array as returned from API
   *
   * @internal param $ string accountsID ID from Accounting system*
   * @return array|bool $accountsContact Contact Object/ array as expected by civicrm
   */
  function mapToCiviCRM($contact) {
      $new_contact=array(
          'id' => '1',
          'contact_type' => 'Individual',
          'contact_sub_type' => '',
          'do_not_email' => 0,
          'do_not_phone' => 0,
          'do_not_mail' => 0,
          'do_not_sms' => 0,
          'do_not_trade' => 0,
          'is_opt_out' => 0,
          'legal_identifier' => '',
          'external_identifier' => $contact['ClientID'],
          'sort_name' => '',
          'display_name' => $contact['CompanyName'],
          'nick_name' => '',
          'legal_name' => '',
          'image_URL' => '',
          'preferred_communication_method' => '',
          'preferred_language' => 'en_US',
          'preferred_mail_format' => 'Both',
          'first_name' => $contact['PrimaryContact']['FirstName'],
          'middle_name' => '',
          'last_name' => $contact['PrimaryContact']['Surname'],
          'prefix_id' => '',
          'suffix_id' => '',
          'formal_title' => '',
          'communication_style_id' => '',
          'email_greeting_id' => '1',
          'email_greeting_custom' => '',
          'email_greeting_display' => '',
          'postal_greeting_id' => '1',
          'postal_greeting_custom' => '',
          'postal_greeting_display' => '',
          'addressee_id' => '1',
          'addressee_custom' => '',
          'addressee_display' => '',
          'job_title' => '',
          'gender_id' => '',
          'birth_date' => '',
          'is_deceased' => 0,
          'deceased_date' => '',
          'household_name' => '',
          'primary_contact_id' => '',
          'organization_name' => $contact['CompanyName'],
          'sic_code' => '',
          'user_unique_id' => '',
        );
      return $new_contact;
     
  }
  
  /**
   * Map civicrm Array to Accounts package field names
   *
   * @param array $contact
   *          Contact Array as returned from API
   * @param $accountsID
   *
   * @internal param $ string accountsID ID from Accounting system*          string accountsID ID from Accounting system
   * @return array|bool $accountsContact Contact Object/ array as expected by accounts package
   */
  function mapToAccounts($contact, $accountsID=null, $search=false) {
      if ($contact==false) {
          $ReturnCount=200;
      } else {
          $ReturnCount=1;
      }
      if (empty($contact['country_id'])) {
          $contact['country_id']='1226'; //TODO: get the default country code
      }
      if ($search) {
          $new_contact =array(
                "SearchParameters" => array(
                    "ReturnCount"=>$ReturnCount,
                    "Offset"=>0,
                    "OrderResultsBy"=>'CompanyName',
                    "OrderDirection"=>'ASC',
                    "CompanyName"=>$contact['display_name'],
                    "AccountReference"=>$accountsID
                )
              );
      } else {

          $countryIsoCodes=civicrm_api("Constant","get", array ('version' =>'3', 'name' =>'countryIsoCode'));
          $countryIsoCodes=array_flip($countryIsoCodes['values']);

          
            $new_contact =array(
              "ClientDetails" => array(  
                  "ClientID" => $accountsID,
                  "CompanyName" => $contact['display_name'],
                  "AccountReference" =>$contact['contact_id'],
                  "AddressLine1" => $contact['street_address'],
                  "AddressLine2" => $contact['supplemental_address_1'],
                  "AddressLine3" => $contact['supplemental_address_2'],
                  "Town" => $contact['city'],
                  "CountryISO" => array_search($contact['country_id'], $countryIsoCodes),
                  "Postcode" => $contact['postal_code'],
                  //"EmailAddress" => CRM_Utils_Rule::email($contact['email']) ? $contact['email'] : '',
                  //"ContactNumber" => $contact['contact_id'],
                  "Preferences" => array (
                      "DefaultSendMethod" => 'EMAIL',
                      "DefaultCurrency" => 'GBP',
                      "DefaultTerm" => '14',
                  ),
                )
            );
       }
       
    $proceed = TRUE;
    CRM_Accountsync_Hook::accountPushAlterMapped('contact', $contact, $proceed, $new_contact);
    if (! $proceed) {
      return FALSE;
    }
    return $new_contact;
  }
}
