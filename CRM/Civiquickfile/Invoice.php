<?php

class CRM_Civiquickfile_Invoice extends CRM_Civiquickfile_Base {
  protected $_plugin = 'quickfile';

  /**
   * pull contacts from Xero and store them into civicrm_account_contact
   * We call the civicrm_accountPullPreSave hook so other modules can alter if required
   *
   * @param array $params
   *
   * @return bool
   * @throws API_Exception
   * @throws CRM_Core_Exception
   */
  function pull($params) {
   // $result = $this->getSingleton()->Invoice_Search(false, $this->formatDateForXero($params['start_date']), array ("Type" => "ACCREC" ));
    $result = $this->getSingleton()->Invoice_Search($this->mapToAccounts(null, null, true), $this->formatDateForXero($params['start_date']), array ("Type" => "ACCREC" ));
    if(!is_array($result)){
      throw new API_Exception('Sync Failed', 'quickfile_retrieve_failure', (array) $result);
    }
    $errors = $result['Error'];
    if(!empty($errors)){
      throw new API_Exception('Sync Failed', 'quickfile_retrieve_failure', (array) $result);
    }
    if (!empty($result['Body'])){
        $RecordsetCount = $result['Body']['RecordsetCount'];
        $ReturnCount = $result['Body']['ReturnCount'];
      $invoices = $result['Body']['Record'];
//      if(isset($invoices['InvoiceID'])) {
//        // the return syntax puts the contact only level higher up when only one contact is involved
//        $invoices  = array($invoices );
//      }
      foreach($invoices as $invoice){
        $save = TRUE;
        $params = array(
          'contribution_id' => CRM_Utils_Array::value('InvoiceNumber', $invoice),
          'accounts_modified_date' => $invoice['UpdatedDateUTC'],
          'plugin' => 'quickfile',
          'accounts_invoice_id' => $invoice['InvoiceID'],
          'accounts_data' => json_encode($invoice),
          'accounts_status_id' => $this->mapStatus($invoice['Status']),
          'accounts_needs_update' => 0,
        );
        CRM_Accountsync_Hook::accountPullPreSave('invoice', $invoice, $save, $params);
        if(!$save) {
          continue;
        }
        try {
          $params['id'] = civicrm_api3('account_invoice', 'getvalue', array(
            'return' => 'id',
            'accounts_invoice_id' => $invoice['InvoiceID'],
            'plugin' => $this->_plugin,
          ));
        }
        catch (CiviCRM_API3_Exception $e) {
          // this is an update - but lets just check the contact id doesn't exist in the account_contact table first
          // e.g if a list has been generated but not yet pushed
          try {
            $existing = civicrm_api3('account_invoice', 'getsingle', array(
              'return' => 'id',
              'contribution_id' => $invoice['InvoiceNumber'],
              'plugin' => $this->_plugin,
            ));
            $params['id'] = $existing['id'];
            if(!empty($existing['accounts_invoice_id']) && $existing['accounts_invoice_id'] != $invoice['InvoiceID']) {
              // no idea how this happened or what it means - calling function can catch & deal with it
              throw new CRM_Core_Exception(ts('Cannot update invoice'), 'data_error', $invoice);
            }
          }
          catch (CiviCRM_API3_Exception $e) {
            // ok - it IS an update
          }
        }
        try {
          $result = civicrm_api3('account_invoice', 'create', $params);
        }
        catch (CiviCRM_API3_Exception $e) {
          $errors[] = ts('Failed to store ') . $invoice['InvoiceNumber'] . ' (' . $invoice['InvoiceID'] . ' )'
          . ts(' with error ') . $e->getMessage()
          . ts('Invoice Pull failed');
        }
      }
    }
    if($errors) {
      // since we expect this to wind up in the job log we'll print the errors
      throw new CRM_Core_Exception(ts('Not all invoices were saved') . print_r($errors, TRUE), 'incomplete', $errors);
    }
    return TRUE;
  }

  /**
   * push contacts to Xero from the civicrm_account_contact with 'needs_update' = 1
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
    $records = civicrm_api3('account_invoice', 'get', array(
      'accounts_needs_update' => 1,
      'plugin' => 'quickfile',
      )
    );
    $errors = array();

    //@todo pass limit through from params to get call
    foreach ($records['values'] as $record) {
      try {
        $accountsInvoiceID = isset($record['accounts_invoice_id']) ? $record['accounts_invoice_id'] : NULL;
        $contributionID = $record['contribution_id'];
        $civiCRMInvoice  = civicrm_api3('account_invoice', 'getderived', array(
          'id' => $contributionID
        ));
        $civiCRMInvoice  = $civiCRMInvoice['values'][$contributionID];
        if(empty($civiCRMInvoice) || $civiCRMInvoice['contribution_status_id'] == 3) {
          $accountsInvoice = $this->mapCancelled($contributionID, $accountsInvoiceID);
        }
        else {
          $accountsInvoice = $this->mapToAccounts($civiCRMInvoice, $accountsInvoiceID);
        }
        $result = $this->getSingleton()->Invoice_Create($accountsInvoice[0]);
        $responseErrors = $this->validateResponse($result);
        if($responseErrors) {
          if(in_array('Invoice not of valid status for modification', $responseErrors)) {
            // we can't update in Xero as it is approved or voided so let's not keep trying
            $record['accounts_needs_update'] = 0;
          }
          $record['error_data'] = json_encode($responseErrors);
        }
        else {
          $record['error_data'] = 'null';
          if(empty($record['accounts_invoice_id'])) {
            $record['accounts_invoice_id'] = $result['Invoices']['Invoice']['InvoiceID'];
          }
          $record['accounts_modified_date'] = $result['Invoices']['Invoice']['UpdatedDateUTC'];
          $record['accounts_data'] = json_encode($result['Invoices']['Invoice']);
          $record['accounts_status_id'] = $this->mapStatus($result['Invoices']['Invoice']['Status']);
          $record['accounts_needs_update'] = 0;
        }
        //this will update the last sync date & anything hook-modified
        unset($record['last_sync_date']);
        if(empty($record['accounts_modified_date'])) {
          unset($record['accounts_modified_date']);
        }
        civicrm_api3('account_invoice', 'create', $record);
      }
      catch (CiviCRM_API3_Exception $e) {
        $errors[] = ts('Failed to store ') . $record['contribution_id'] . ' (' . $record['accounts_contact_id'] . ' )'
          . ts(' with error ') . $e->getMessage() . print_r($responseErrors, TRUE)
          . ts('Invoice Push failed');
      }
    }
    if($errors) {
      // since we expect this to wind up in the job log we'll print the errors
      throw new CRM_Core_Exception(ts('Not all invoices were saved') . print_r($errors, TRUE), 'incomplete', $errors);
    }
    return TRUE;
  }

  /**
   * Map civicrm Array to Accounts package field names
   *
   * @param array $invoiceData - require
   *  contribution fields
   *   - line items
   *   - receive date
   *   - source
   *   - contact_id
   * @param $accountsID
   * @param $search
   *   - boolean return invoice search array
   *
   * @internal param \accountsID $string ID from Accounting system
   * @return array|bool $accountsContact Contact Object/ array as expected by accounts package
   */
  function mapToAccounts($invoiceData, $accountsID, $search) {
if ($search) {
          $new_invoice =array(
                "SearchParameters" => array(
                    "ReturnCount"=>200,
                    "Offset"=>0,
                    "OrderResultsBy"=>'ClientName',
                    "OrderDirection"=>'ASC',
                    "InvoiceType"=>'INVOICE'
                )
              );
      } else {
            $defaultAccountCode = civicrm_api('setting', 'getvalue', array(
              'group' => 'Quickfile Settings',
              'name' => 'quickfile_default_revenue_account',
              'version' => 3,
            ));

            $ItemLines = array();
            foreach ($invoiceData['line_items'] as $ItemLine) {
              $ItemLines[] = array(
                  "ItemLine" => array(
                        "ItemID"      => '0', //$ItemLine['id'],
                        "ItemName"    => $ItemLine['entity_table'],
                        "ItemDescription"    => $ItemLine['display_name'] . $ItemLine['label'],
                        "ItemNominalCode"    => !empty($ItemLine['accounting_code']) ? $ItemLine['accounting_code'] : $defaultAccountCode,
//                        "Tax1" =>array(
//                            //@todo civicrm does not appear to pass tax through
//                              "TaxName" =>'',
//                              "TaxPercentage" =>'',
//                            ),
                        "UnitCost"    => $ItemLine['unit_price'],
                        "Qty"    => $ItemLine['qty'],        
                        )
                    );
            }


            $new_invoice = array("InvoiceData" => array(
              "InvoiceType" => 'INVOICE',
              "ClientID" => $accountsID, //$invoiceData['contact_id'],
              "ClientAddress"  => array(
                  //@todo add address as <![CDATA[123 Test Street<br/>Manchester<br/>MA1 5TH<br/>United Kingdom]]>
                  "Address" => '',
                  "CountryISO" => 'GB', //CRM_Core_Config::singleton()->defaultContactCountry,  //@todo add default country code and translate crm counry to ISO
                ),
                "Currency"  => !empty($invoiceData['currency']) ? $invoiceData['currency'] : CRM_Core_Config::singleton()->defaultCurrency,
                "TermDays" =>'14', //@todo add a setting to set this
                "Language" => 'en', //@todo get default value from civicrm settings
                "SingleInvoiceData" => array(
                   "IssueDate" => substr($invoiceData['receive_date'], 0, 10),
                ),
              "InvoiceLines" => array('ItemLines' => $ItemLines),
            ));

            $proceed = TRUE;
            CRM_Accountsync_Hook::accountPushAlterMapped('invoice', $invoiceData, $proceed, $new_invoice);
            if (!$proceed) {
              return FALSE;
            }

            $this->validatePrerequisites($new_invoice);
            $new_invoice = array (
              $new_invoice
            );
      }
    return $new_invoice;
  }

  function mapCancelled($contributionID, $accounts_invoice_id) {
    $newInvoice = array(
      'Invoice' => array(
        'InvoiceID'     => $accounts_invoice_id,
        'InvoiceNumber' => $contributionID,
        'Type'          => 'ACCREC',
        'Reference' =>  'Cancelled',
        'Date'  => date('Y-m-d',strtotime(now)),
        'DueDate'  => date('Y-m-d',strtotime(now)),
        'Status'  => 'DRAFT',
        'LineAmountTypes' =>'Exclusive',
        'LineItems' => array(
          'LineItem' => array(
            'Description' => 'Cancelled',
            'Quantity' => 0,
            'UnitAmount'=> 0,
            'AccountCode'=> 200,
          )
        ),
      )
    );
    return $newInvoice;
  }

  /**
   * Map Xero Status values against CiviCRM status values
   *
   */
  function mapStatus($status) {
    $statuses = array(
      'PAIDFULL' => 1,
      'DELETED' => 3,
      'VOIDED' => 3,
      'DRAFT' => 2,
      'AUTHORISED' => 2,
      'SUBMITTED' => 2,
    );
    return $statuses[$status];
  }

  /**
   * Validate an invoice by checking the tracking category exists (if set)
   * @param array $invoice array ready for Xero
   */
  function validatePrerequisites($invoice){
    if(empty($invoice['LineItems'])) {
      return;
    }
    foreach ($invoice['LineItems']['LineItem'] as $lineItems) {
      if(array_key_exists('LineItem', $lineItems)) {
        // multiple line items  - need to go one deeper
        foreach ($lineItems as $lineItem) {
          $this->validateTrackingCategory($lineItem);
        }
      }
      else {
        $this->validateTrackingCategory($lineItems);
      }
    }
  }

  /**
   * Check values in Line Item against retrieved list of Tracking Categories
   * @param unknown $lineItem
   * @throws CRM_Core_Exception
   */
  function validateTrackingCategory($lineItem) {
    if(empty($lineItem['TrackingCategory'])) {
      return;
    }
    static $trackingOptions = array();
    if(empty($trackingOptions)){
      $trackingOptions = civicrm_api3('xerosync', 'trackingcategorypull', array());
      $trackingOptions = $trackingOptions['values'];
    }
    foreach ($lineItem['TrackingCategory'] as $tracking) {
      if(!array_key_exists($tracking['Name'], $trackingOptions)
      || !in_array($tracking['Option'], $trackingOptions[$tracking['Name']])) {
        throw new CRM_Core_Exception(ts('Tracking Category Does Not Exist ') . $tracking['Name'] . ' ' . $tracking['Option'],'invalid_tracking', $tracking);
      }
    }
  }
}
