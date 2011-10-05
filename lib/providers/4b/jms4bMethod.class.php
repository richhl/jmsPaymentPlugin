<?php
/*
 * Copyright 2011 Richard C. Hidalgo Lorite
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


/**
 * Implements the payment method for 4b
 * 
 * @package jmsPaymentPlugin
 * @subpackage providers
 * @author Richard C. Hidalgo <rich at argonauta.org>
 */
/* Reference information visiting https://tpv.4b.es/tpvv/consultas/
 * /doc/Documentos%20Errores/GU%C3%8DA%20R%C3%81PIDA%20DE%20CONSULTA%20PASAT%20INTERNET.htm
 * or googling "pasat internet manual"
 */
class jms4bPaymentMethod extends jmsPaymentMethod
{
  
  //private $_callerServices;
  
  /**
   * We need to make some adjustments to the loading procedure, 
   * so we can use the PayPal API library
   */
  /*
  public function __construct()
  {
    // unfortunately, the PayPal API was not made for PHP 5
    // so, we need to disable some error checks
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    
    // append the PayPal API base dir to the include path
    ini_set('include_path', ini_get('include_path').PATH_SEPARATOR
                            .sfConfig::get('sf_lib_dir').'/vendor/PayPal/');
    
    require_once 'PayPal.php';
    require_once 'PayPal/Profile/Handler/Array.php';
    require_once 'PayPal/Profile/API.php';
  }
 */ 
  
/**
   * TO_BE_IMPLEMENTED: 4b admite que se realicen preautorizaciones
   * que consisten en una petición por parte del comercio de que
   * se confirme que la tarjeta es válida y dispone de saldo para
   * el pago.
   * Luego el comercio puede confirmar el pago en un plazo determinado
   * admitiéndose fluctuaciones del 10% del importe que se haya preautorizado.
   * 
   */
/*
  public function approve(jmsPaymentMethodData $data, $retry = false)
  {
  }
*/
  
  /**
   * Deposits a transaction. This method is not intended to call any
   * api belonging to 4b system as this api does not exist. The first
   * action for depositing has to be a POST submission to an URL outside
   * of the online shop pages where the purchaser has to fill payment info.
   * When this is done 4b server calls in return online shop url.
   * You have to connect that url with this method.
   * You can inform 4b about the url that leads to this method in the
   * field labeled: "URL que graba el resultado en la BD del comercio"
   * https://tpv.4b.es/config
   * 
   * @see plugins/jmsPaymentPlugin/lib/method/jmsPaymentMethod#deposit($data, $retry)
   */
  public function deposit(jmsPaymentMethodData $data, $retry = false)
  {
    //If this is not called from 4b systems we want to launch a user interaction exception

    //Check call originated from 4b simulation or production servers.
    $caller_address =  sfContext::getInstance()->getRequest()->getRemoteAddress(); //This call is far from the paymentDemo sfAction
										   //to not taint the 'general' logic implemented there.
    $production_ip = $this->getProductionIp();
    $simulation_ip = $this->getSimulationIp();

    //This block is going to be executed when call is from 'shop' pages and
    //4b pages to be filled by the user are going to be shown as result.
    if ($caller_address != $production_ip && $caller_address != $simulation_ip) { //call to deposit not from 4b servers
	$production_url = $this->getProductionUrl(); 
	$simulation_url = $this->getSimulationUrl(); 
        $_4b_conection_url = !$this->isDebug() ?
	    $production_url : $simulation_url;

	// throw 
	$exception = new jmsPaymentUserActionRequiredException(
		new jmsPaymentUserActionVisitURL($_4b_conection_url)
		);
	$data->setValue('transaction_from_ip',$caller_address);
	$exception->setPaymentMethodData($data);
	throw $exception;
    }

    //This block is going to be executed when call is from 4b server
    $amount_value = $data->getAmount();
      
    if ($data->getValue('transaction_result') == 1) { //Success
	$data->setResponseCode($data->getValue('transaction_id'));
	$data->setReasonCode('Approval with 4b approval code: '.$data->getValue('transaction_approval_code'));
	$data->setProcessedAmount($amount_value); //Processed amount equals requested.
    } else { //Failed
	$data->setResponseCode($data->getValue('transaction_error_code'));
	$data->setReasonCode($data->getValue('transaction_error_description'));
        $e = new jmsPaymentException('Payment could not be completed. Reason: '.$data->getReasonCode());
        $e->setPaymentMethodData($data);
        throw $e;
    }
  }
  
  protected function getStoreKey()
  {
    $config = sfConfig::get('app_jmsPaymentPlugin__4b');
    if (!isset($config['store']))
      throw new RuntimeException('You must set a 4b provided store key.');
      
    return $config['store'];
  }
  
  protected function getProductionUrl()
  {

    $config = sfConfig::get('app_jmsPaymentPlugin__4b');
    if (!isset($config['production_url']))
      throw new RuntimeException('You must configure a "production url" for 4b in your app.yml');

    return $config['production_url'];
  }

  protected function getProductionIp()
  {

    $config = sfConfig::get('app_jmsPaymentPlugin__4b');
    if (!isset($config['production_ip']))
      throw new RuntimeException('You must configure a "production ip" for 4b in your app.yml');

    return $config['production_ip'];
  }

  protected function getSimulationUrl()
  {

    $config = sfConfig::get('app_jmsPaymentPlugin__4b');
    if (!isset($config['simulation_url']))
      throw new RuntimeException('You must configure a "simulation url" for 4b in your app.yml');

    return $config['simulation_url'];
  }

  protected function getSimulationIp()
  {

    $config = sfConfig::get('app_jmsPaymentPlugin__4b');
    if (!isset($config['simulation_ip']))
      throw new RuntimeException('You must configure a "simulation ip" for 4b in your app.yml');

    return $config['simulation_ip'];
  }

  /**
   * Creates a CallerServices object with our credentials
   * 
   * @throws RuntimeException if the API Caller could not be initialized
   * @return CallerServices
   */
/*
  public function getCallerServices()
  {
    if ($this->_callerServices === null)
    {
      $username = $this->getUsername();
      $signature = $this->getSignature();
      $password = $this->getPassword();
      $environment = $this->isDebug() ? 'Sandbox' : 'Live';
      
      $handler = ProfileHandler_Array::getInstance(array(
        'username'           => $username,
        'certificateFile'    => null,
        'signature'          => $signature,
        'subject'            => null,
        'environment'        => $environment,
      ));
      
      $profile = APIProfile::getInstance($username, $handler);
      $profile->setAPIPassword($password);
                  
      $caller = PayPal::getCallerServices($profile);
      
      // if we are in debug mode, ignore any invalid SSL certificates
      // TODO: Check if we also need this in production
      if ($this->isDebug())
      {
        $caller->setOpt('curl', CURLOPT_SSL_VERIFYPEER, 0);
        $caller->setOpt('curl', CURLOPT_SSL_VERIFYHOST, 0);
      }
        
      if (PayPal::isError($caller))
        throw new RuntimeException('The API Caller could not be initialized: '
                                   .$caller->getMessage());
        
      $this->_callerServices = $caller;
    }
        
    return $this->_callerServices;
  }
*/
}
