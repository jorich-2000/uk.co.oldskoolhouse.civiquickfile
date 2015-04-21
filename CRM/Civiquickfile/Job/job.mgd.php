<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'Civiquickfile Contact Push Job',
    'entity' => 'Job',
    'params' =>
    array (
      'version' => 3,
      'name' => 'Civiquickfile Contact Push Job',
      'description' => 'Push updated contacts to Quickfile',
      'api_entity' => 'Civiquickfile',
      'api_action' => 'contactpush',
      'run_frequency' => 'Always',
      'parameters' => 'plugin=quickfile',
    ),
  ),
  1 =>
  array (
    'name' => 'Civiquickfile Contact Pull Job',
    'entity' => 'Job',
    'params' =>
    array (
      'version' => 3,
      'name' => 'Civiquickfile Contact Pull Job',
      'description' => 'Pull updated contacts from Quickfile',
      'api_entity' => 'Civiquickfile',
      'api_action' => 'contactpull',
      'run_frequency' => 'Always',
      'parameters' => 'plugin=quickfile, start_date=yesterday',
    ),
  ),
  array (
    'name' => 'Civiquickfile Invoice Push Job',
    'entity' => 'Job',
    'params' =>
    array (
      'version' => 3,
      'name' => 'Civiquickfile Invoice Push Job',
      'description' => 'Push updated invoices from Quickfile',
      'api_entity' => 'Civiquickfile',
      'api_action' => 'invoicepush',
      'run_frequency' => 'Always',
      'parameters' => 'plugin=quickfile',
    ),
  ),
  array (
    'name' => 'Civiquickfile Invoice Pull Job',
    'entity' => 'Job',
    'params' =>
    array (
      'version' => 3,
      'name' => 'Civiquickfile Invoice Pull Job',
      'description' => 'Pull updated invoices from Quickfile',
      'api_entity' => 'Civiquickfile',
      'api_action' => 'invoicepull',
      'run_frequency' => 'Always',
      'parameters' => 'plugin=quickfile, start_date=yesterday',
    ),
  ),
);