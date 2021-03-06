<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 * File for the CiviCRM APIv3 Contribution functions
 *
 * @package CiviCRM_APIv3
 * @subpackage API_Contribute
 *
 * @copyright CiviCRM LLC (c) 2004-2013
 * @version $Id: Contribution.php 30486 2010-11-02 16:12:09Z shot $
 *
 */

/**
 * Add or update a contribution
 *
 * @param  array   $params           (reference ) input parameters
 *
 * @return array  Api result array
 * @static void
 * @access public
 * @example ContributionCreate.php
 * {@getfields Contribution_create}
 */
function civicrm_api3_contribution_create(&$params) {
  $values = array();

  _civicrm_api3_contribute_format_params($params, $values);

  _civicrm_api3_custom_format_params($params, $values, 'Contribution');
  $values["contact_id"] = CRM_Utils_Array::value('contact_id', $params);
  $values["source"] = CRM_Utils_Array::value('source', $params);
  //legacy soft credit handling
  if(!empty($params['soft_credit_to'])){
    $values['soft_credit'] = array(array(
      'contact_id' => $params['soft_credit_to'],
      'amount' => $params['total_amount']));
  }
  $ids = array();
  if (CRM_Utils_Array::value('id', $params)) {
    $ids['contribution'] = $params['id'];
    // CRM-12498
    if (CRM_Utils_Array::value('contribution_status_id', $params)) {
      $error = array();
      //throw error for invalid status change
      CRM_Contribute_BAO_Contribution::checkStatusValidation(NULL, $params, $error);
      if (array_key_exists('contribution_status_id', $error)) {
        return civicrm_api3_create_error($error['contribution_status_id']);
      }
    }
  }
  $contribution = CRM_Contribute_BAO_Contribution::create($values, $ids);

  if (is_a($contribution, 'CRM_Core_Error')) {
    return civicrm_api3_create_error($contribution->_errors[0]['message']);
  }
  _civicrm_api3_object_to_array($contribution, $contributeArray[$contribution->id]);

  return civicrm_api3_create_success($contributeArray, $params, 'contribution', 'create', $contribution);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_contribution_create_spec(&$params) {
  $params['contact_id']['api.required'] = 1;
  $params['total_amount']['api.required'] = 1;
  $params['payment_instrument_id']['api.aliases'] = array('payment_instrument');
  $params['payment_processor'] = array(
    'name' => 'payment_processor',
    'title' => 'Payment Processor ID',
    'description' => 'ID of payment processor used for this contribution',
    // field is called payment processor - not payment processor id but can only be one id so
    // it seems likely someone will fix it up one day to be more consistent - lets alias it from the start
    'api.aliases' => array('payment_processor_id'),
  );
  $params['financial_type_id']['api.aliases'] = array('contribution_type_id', 'contribution_type');
  $params['financial_type_id']['api.required'] = 1;
  $params['note'] = array(
    'name' => 'note',
    'uniqueName' => 'contribution_note',
    'title' => 'note',
    'type' => 2,
    'description' => 'Associated Note in the notes table',
  );
  $params['soft_credit_to'] = array(
    'name' => 'soft_credit_to',
    'title' => 'Soft Credit contact ID',
    'type' => 1,
    'description' => 'ID of Contact to be Soft credited to',
    'FKClassName' => 'CRM_Contact_DAO_Contact',
  );
  // note this is a recommended option but not adding as a default to avoid
  // creating unecessary changes for the dev
  $params['skipRecentView'] = array(
    'name' => 'skipRecentView',
    'title' => 'Skip adding to recent view',
    'type' => 1,
    'description' => 'Do not add to recent view (setting this improves performance)',
  );
  $params['skipLineItem'] = array(
    'name' => 'skipLineItem',
    'title' => 'Skip adding line items',
    'type' => 1,
    'api.default' => 0,
    'description' => 'Do not add line items by default (if you wish to add your own)',
  );
}

/**
 * Delete a contribution
 *
 * @param  array   $params           (reference ) input parameters
 *
 * @return boolean        true if success, else false
 * @static void
 * @access public
 * {@getfields Contribution_delete}
 * @example ContributionDelete.php
 */
function civicrm_api3_contribution_delete($params) {

  $contributionID = CRM_Utils_Array::value('contribution_id', $params) ? $params['contribution_id'] : $params['id'];
  if (CRM_Contribute_BAO_Contribution::deleteContribution($contributionID)) {
    return civicrm_api3_create_success(array($contributionID => 1));
  }
  else {
    return civicrm_api3_create_error('Could not delete contribution');
  }
}

/**
 * modify metadata. Legacy support for contribution_id
 */
function _civicrm_api3_contribution_delete_spec(&$params) {
  $params['id']['api.aliases'] = array('contribution_id');
}

/**
 * Retrieve a set of contributions, given a set of input params
 *
 * @param  array   $params           (reference ) input parameters
 * @param array    $returnProperties Which properties should be included in the
 * returned Contribution object. If NULL, the default
 * set of properties will be included.
 *
 * @return array (reference )        array of contributions, if error an array with an error id and error message
 * @static void
 * @access public
 * {@getfields Contribution_get}
 * @example ContributionGet.php
 */
function civicrm_api3_contribution_get($params) {

  $options          = _civicrm_api3_get_options_from_params($params, TRUE,'contribution','get');
  $sort             = CRM_Utils_Array::value('sort', $options, NULL);
  $offset           = CRM_Utils_Array::value('offset', $options);
  $rowCount         = CRM_Utils_Array::value('limit', $options);
  $smartGroupCache  = CRM_Utils_Array::value('smartGroupCache', $params);
  $inputParams      = CRM_Utils_Array::value('input_params', $options, array());
  $returnProperties = CRM_Utils_Array::value('return', $options, NULL);
  if (empty($returnProperties)) {
    $returnProperties = CRM_Contribute_BAO_Query::defaultReturnProperties(CRM_Contact_BAO_Query::MODE_CONTRIBUTE);
  }

  $newParams = CRM_Contact_BAO_Query::convertFormValues($inputParams);
  $query = new CRM_Contact_BAO_Query($newParams, $returnProperties, NULL,
    FALSE, FALSE, CRM_Contact_BAO_Query::MODE_CONTRIBUTE
  );
  list($select, $from, $where, $having) = $query->query();

  $sql = "$select $from $where $having";

  if (!empty($sort)) {
    $sql .= " ORDER BY $sort ";
  }
  $sql .= " LIMIT $offset, $rowCount ";
  $dao = CRM_Core_DAO::executeQuery($sql);

  $contribution = array();
  while ($dao->fetch()) {
    //CRM-8662
    $contribution_details = $query->store($dao);
    $softContribution = CRM_Contribute_BAO_ContributionSoft::getSoftContribution($dao->contribution_id , TRUE);
    $contribution[$dao->contribution_id] = array_merge($contribution_details, $softContribution);
    if(isset($contribution[$dao->contribution_id]['financial_type_id'])){
      $contribution[$dao->contribution_id]['financial_type_id'] = $contribution[$dao->contribution_id]['financial_type_id'];
    }
    // format soft credit for backward compatibility
    _civicrm_api3_format_soft_credit($contribution[$dao->contribution_id]);
  }
  return civicrm_api3_create_success($contribution, $params, 'contribution', 'get', $dao);
}

/**
 * This function is used to format the soft credit for backward compatibility
 * as of v4.4 we support multiple soft credit, so now contribution returns array with 'soft_credit' as key
 * but we still return first soft credit as a part of contribution array
 */
function _civicrm_api3_format_soft_credit(&$contribution) {
  if (!empty($contribution['soft_credit'])) {
    $contribution['soft_credit_to'] = $contribution['soft_credit'][1]['contact_id'];
    $contribution['soft_credit_id'] = $contribution['soft_credit'][1]['soft_credit_id'];
  }
}

/**
 * Adjust Metadata for Get action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_contribution_get_spec(&$params) {
  $params['contribution_test']['api.default'] = 0;
  $params['financial_type_id']['api.aliases'] = array('contribution_type_id');
  $params['contact_id'] = $params['contribution_contact_id'];
  $params['contact_id']['api.aliases'] = array('contribution_contact_id');
  unset($params['contribution_contact_id']);
}

/**
 * take the input parameter list as specified in the data model and
 * convert it into the same format that we use in QF and BAO object
 *
 * @param array  $params       Associative array of property name/value
 * pairs to insert in new contact.
 * @param array  $values       The reformatted properties that we can use internally
 * '
 *
 * @return array|CRM_Error
 * @access public
 */
function _civicrm_api3_contribute_format_params($params, &$values, $create = FALSE) {
//legacy way of formatting from v2 api - v3 way is to define metadata & do it in the api layer
  _civicrm_api3_filter_fields_for_bao('Contribution', $params, $values);
  return array();
}

/**
 * Process a transaction and record it against the contact.
 *
 * @param  array   $params           (reference ) input parameters
 *
 * @return array (reference )        contribution of created or updated record (or a civicrm error)
 * @static void
 * @access public
 *
 */
function civicrm_api3_contribution_transact($params) {
  $required = array('amount');
  foreach ($required as $key) {
    if (!isset($params[$key])) {
      return civicrm_api3_create_error("Missing parameter $key: civicrm_contribute_transact() requires a parameter '$key'.");
    }
  }

  // allow people to omit some values for convenience
  // 'payment_processor_id' => NULL /* we could retrieve the default processor here, but only if it's missing to avoid an extra lookup */
  $defaults = array(
    'payment_processor_mode' => 'live',
  );
  $params = array_merge($defaults, $params);

  // clean up / adjust some values which
  if (!isset($params['total_amount'])) {
    $params['total_amount'] = $params['amount'];
  }
  if (!isset($params['net_amount'])) {
    $params['net_amount'] = $params['amount'];
  }
  if (!isset($params['receive_date'])) {
    $params['receive_date'] = date('Y-m-d');
  }
  if (!isset($params['invoiceID']) && isset($params['invoice_id'])) {
    $params['invoiceID'] = $params['invoice_id'];
  }

  $paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment($params['payment_processor_id'], $params['payment_processor_mode']);
  if (civicrm_error($paymentProcessor)) {
    return $paymentProcessor;
  }

  $payment = &CRM_Core_Payment::singleton($params['payment_processor_mode'], $paymentProcessor);
  if (civicrm_error($payment)) {
    return $payment;
  }

  $transaction = $payment->doDirectPayment($params);
  if (civicrm_error($transaction)) {
    return $transaction;
  }

  // but actually, $payment->doDirectPayment() doesn't return a
  // CRM_Core_Error by itself
  if (get_class($transaction) == 'CRM_Core_Error') {
    $errs = $transaction->getErrors();
    if (!empty($errs)) {
      $last_error = array_shift($errs);
      return CRM_Core_Error::createApiError($last_error['message']);
    }
  }

  $contribution = civicrm_api('contribution', 'create', $params);
  return $contribution['values'];
}
/**
 * Send a contribution confirmation (receipt or invoice)
 * The appropriate online template will be used (the existence of related objects
 * (e.g. memberships ) will affect this selection
 * @param array $params input parameters
 * {@getfields Contribution_sendconfirmation}
 * @return array  Api result array
 * @static void
 * @access public
 *
 */
function civicrm_api3_contribution_sendconfirmation($params) {
  $contribution = new CRM_Contribute_BAO_Contribution();
  $contribution->id = $params['id'];
  if (! $contribution->find(TRUE)) {
    throw new Exception('Contribution does not exist');
}
  $input = $ids = $cvalues = array('receipt_from_email' => $params['receipt_from_email']);
  $contribution->loadRelatedObjects($input, $ids, FALSE, TRUE);
  $contribution->composeMessageArray($input, $ids, $cvalues, FALSE, FALSE);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_contribution_sendconfirmation_spec(&$params) {
  $params['id'] = array(
    'api.required' => 1,
    'title' => 'Contribution ID'
  );
  $params['receipt_from_email'] = array(
    'api.required' =>1,
    'title' => 'From Email (required until someone provides a patch :-)',

  );
}

/**
 * Complete an existing (pending) transaction, updating related entities (participant, membership, pledge etc)
 * and taking any complete actions from the contribution page (e.g. send receipt)
 *
 * @todo - most of this should live in the BAO layer but as we want it to be an addition
 * to 4.3 which is already stable we should add it to the api layer & re-factor into the BAO layer later
 *
 * @param array $params input parameters
 * {@getfields Contribution_completetransaction}
 * @return array  Api result array
 * @static void
 * @access public
 *
 */
function civicrm_api3_contribution_completetransaction(&$params) {

  $input = $ids = array();
  $contribution = new CRM_Contribute_BAO_Contribution();
  $contribution->id = $params['id'];
  $contribution->find(TRUE);
  if(!$contribution->id == $params['id']){
    throw new API_Exception('A valid contribution ID is required', 'invalid_data');
  }
  try {
    if(!$contribution->loadRelatedObjects($input, $ids, FALSE, TRUE)){
      throw new API_Exception('failed to load related objects');
    }
    $objects = $contribution->_relatedObjects;
    $objects['contribution'] = &$contribution;
    $input['component'] = $contribution->_component;
    $input['is_test'] = $contribution->is_test;
    $input['trxn_id']= $contribution->trxn_id;
    $input['amount'] = $contribution->total_amount;
    if(isset($params['is_email_receipt'])){
      $input['is_email_receipt'] = $params['is_email_receipt'];
    }
    // @todo required for base ipn but problematic as api layer handles this
    $transaction = new CRM_Core_Transaction();
    $ipn = new CRM_Core_Payment_BaseIPN();
    $ipn->completeTransaction($input, $ids, $objects, $transaction);
  }
  catch(Exception$e) {
    throw new API_Exception('failed to load related objects' . $e->getMessage() . "\n" . $e->getTraceAsString());
  }
}

function _civicrm_api3_contribution_completetransaction(&$params) {

}
