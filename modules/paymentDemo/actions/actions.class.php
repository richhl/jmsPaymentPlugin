<?php

require_once dirname(__FILE__).'/../lib/BasepaymentDemoActions.class.php';

/**
 * paymentDemo actions.
 * 
 * @package    jmsPaymentPlugin
 * @subpackage paymentDemo
 * @author     Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author     Richard C. Hidalgo Lorite <rich at argonauta.org>
 * @version    SVN: $Id: actions.class.php 12534 2008-11-01 13:38:27Z Kris.Wallsmith $
 */
class paymentDemoActions extends BasepaymentDemoActions
{
  public function preExecute()
  {
    $this->setDemoLayout();
  }

  private function setDemoLayout()
  {
    $this->setLayout(dirname(__FILE__).'/../templates/demoLayout');
  }

  //Convenience method to show kind of interaction needed for 4b
  //to complete a deposit. Once user has completed his/her payment data
  //filling payment form at 4b servers, 4b server stablish a connnection
  //with the online shop to get the 'cart'. This is a convenience example
  //giving 4b a cart of only one product -a hack for not needing to augment
  //the data model of this plugin with orders and products.    
  public function executeGetCartFromPaymentFor4b(sfWebRequest $request)
  {
      $payment = $this->getPaymentFromRequest($request);

      //Audititing connections claiming non existent orders.
      if (!$payment) $this->logMessage('paymentDemoActions::executeGetCartFromPaymentFor4b : Call from ip '.$request->getRemoteAddress().' to get a non existent order with reference number = '.$request->getParameter('order'), 'err');

      $this->forward404Unless($payment);

      //Test that call is asking for a payment with a deposit
      //as current opened transaction.
      if (!$payment->hasOpenTransaction() ) {
	  $errorMessageForUser = "The payment has not initiated a deposit. Is not possible to claim a basket for making a deposit.";
	  $errorMessageForLog = 'paymentDemoActions::executeGetCartFromPaymentFor4b : Call from ip '.$request->getRemoteAddress().' for order '.$payment->id.' with bad logic. '.$errorMessageForUser;
	  $this->logMessage($errorMessageForLog, 'err');
	  throw new LogicException($errorMessageForUser);
      }

      $transaction = $payment->getOpenTransaction();
      if (!$transaction instanceof FinancialDepositTransaction) {
	  $errorMessageForUser = "The payment has another transaction pending. Is not possible to claim a basket for making a deposit.";
	  $errorMessageForLog = 'paymentDemoActions::executeGetCartFromPaymentFor4b : Call from ip '.$request->getRemoteAddress().' for order '.$payment->id.' with bad logic. '.$errorMessageForUser;
	  $this->logMessage($errorMessageForLog, 'err');
	  throw new LogicException($errorMessageForUser);
      }

      //Being that 4b only accepts EUR, we have to check that both payment and
      //deposit transaction are using this currency.
      $depositCurrency = $transaction->currency;
      $paymentCurrency = $payment->currency;

      if ( ($depositCurrency != 'EUR')  || ($paymentCurrency != 'EUR')) {
	  $errorMessageForUser = '4b only accepts EUR as currency and you have defined the payment using '.$paymentCurrency.' and current deposit using '.$depositCurrency;
	  $errorMessageForLog = 'paymentDemoActions::executeGetCartFromPaymentFor4b : Call from ip '.$request->getRemoteAddress().' for order '.$payment->id.' with bad logic. '.$errorMessageForUser;
	  $this->logMessage($errorMessageForLog, 'err');
	  throw new LogicException($errorMessageForUser);
      }

      $depositAmount = $transaction->requested_amount;
      $myPaymentData = $payment->getDataContainer();


      $result = "M978".$depositAmount."\r\n"; //Pasat 4b accepts only eur -code 978-
      //$orderContents = $order->getContents(); If one have a real cart take content.
      $numr = 1; //For a real cart this is the number of items
      $result = $result.$numr."\r\n";
      //foreach ($orderContents as $item) { If one have a real cart iterate it.
      $result = $result.$payment->id."\r\n"; 		//$result = $result.$item['id']."\r\n";
      $result = $result.$myPaymentData->getSubject()."\r\n";	//$result = $result.$item['name']."\r\n";
      $result = $result."1\r\n";			//$result.$item['qty']."\r\n";
      $result = $result.$depositAmount."\r\n"; 		//$result.($item['price'])."\r\n";
      //}

      $this->getResponse()->setHttpHeader('Content-type', "text/plain");

      $this->getResponse()->setContent($result);

      return sfView::NONE;
  }

  public function executeReceiveDepositInfoFrom4b(sfWebRequest $request)
  {
      //Check caller ip
      $caller_ip = $request->getRemoteAddress();
      $config = sfConfig::get('app_jmsPaymentPlugin__4b');
      if ($caller_ip != $config['simulation_ip'] && $caller_ip != $config['production_ip']) {
	      $user_message = "The call does not origin in an ip recognized as being owned by 4b.";
	      $this->logMessage('paymentDemoActions::receiveDepositInfoFrom4b : Call from ip '.$caller_ip.' against security plicy. '.$user_message); 
	      throw new sfSecurityException($user_message);
	      }

      $payment = $this->getPaymentFromRequest($request);

      //Audititing connections claiming non existent orders.
      if (!$payment) $this->logMessage('paymentDemoActions::receiveDepositInfoFrom4b : Call from ip '.$caller_ip.' to get a non existent order with reference number = '.$request->getParameter('order'), 'err');

      $this->forward404Unless($payment);

      //Test that call is asking for a payment with a deposit
      //as current opened transaction.
      try {
	    $this->checkPaymentStatus($payment);
      } catch(LogicException $le) {
	  $errorMessageForLog = 'paymentDemoActions::receiveDepositInfoFrom4b : Call from ip '.$caller_ip.' for order '.$payment->id.' with bad logic. '.$le->getMessage();
	  $this->logMessage($errorMessageForLog, 'err');
	  throw $le;
      }

    $this->copyDepositInfoToPaymentData($request,$payment);
    $payment->performTransaction($payment->getOpenTransaction()); //At this point we now payment has an open transaction for depositing.
  }

  //This function is intended to fill all payment and transaction data provided by the 4b call.
  protected function copyDepositInfoToPaymentData(sfWebRequest $request,$payment) { 
      $data = $payment->getDataContainer();
      if (!$data instanceof _4bPaymentData)
	  throw new LogicException('The payment has a type of data container not suitable for 4b payments.');
      $data->store = $request->getParameter('store');
      $data->order_ref = $request->getParameter('pszPurchorderNum');
      $data->transaction_result = $request->getParameter('result');
      $data->transaction_type = $request->getParameter('tipotrans');
      $data->transaction_from_ip = $request->getRemoteAddress();
      $data->transaction_date = $request->getParameter('pszTxnDate');
      $data->transaction_approval_code = $request->getParameter('pszApprovalCode');
      $data->transaction_id = $request->getParameter('pszTxnID');
      $data->transaction_error_code = $request->getParameter('coderror');
      $data->transaction_error_description = $request->getParameter('deserror');
      $data->save();
  }

  //This function is used by actions called by 4b for checking
  //the payment has the expected status : opened deposit financial transaction.
  protected function checkPaymentStatus($payment) { 
      if (!$payment->hasOpenTransaction() )
	  throw new LogicException("The payment has not initiated any transaction. This method is supposed to be called by 4b as response to an initiated transaction.");

      $transaction = $payment->getOpenTransaction();
      if (!$transaction instanceof FinancialDepositTransaction)
	  throw new LogicException("The payment has another transaction pending not being a deposit. This method is supposed to be called by 4b as response to an initiated deposit transaction.");
  }

}
