<?php

/**
 * Class Vendiro_ApiHandler_Model_Payment_Standard
 */
class Vendiro_ApiHandler_Model_Payment_Standard extends Mage_Payment_Model_Method_Abstract
{

    protected $_code = 'vendiro_standard';

    protected $_canUseInternal              = false;
    protected $_canUseCheckout              = false;
    protected $_canUseForMultishipping      = false;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = false;
    protected $_canReviewPayment            = false;
    protected $_canCreateBillingAgreement   = false;
    protected $_canManageRecurringProfiles  = false;

}