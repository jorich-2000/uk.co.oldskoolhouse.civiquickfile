<?php

class CRM_Civiquickfile_TrackingCategory extends CRM_Civiquickfile_Base {

  /**
   * pull TrackingCategories from Quickfile and temporarily stash them on static
   * we don't want to keep stale ones in our DB - we'll check each time
   * We call the civicrm_accountPullPreSave hook so other modules can alter if required
   *  - I can't think of a reason why they would but it seems consistent
   *
   * @param array $params
   * @throws API_Exception
   */
  function pull($params) {
    static $trackingOptions = array();
    if(empty($trackingOptions)){
      $tc = $this->getSingleton()->TrackingCategories();
      foreach($tc['TrackingCategories']['TrackingCategory'] as $trackingCategory){
        foreach ($trackingCategory['Options']['Option'] as $key =>$value){
          $trackingOptions[$trackingCategory['Name']][] = $value['Name'];
        }
      }
    }
    return $trackingOptions;
  }
}
