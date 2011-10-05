<?php
/**
 * Plugin_4bPaymentData form.
 *
 * @package    jmsPaymentPlugin
 * @subpackage form
 * @author     Richard C. Hidalgo <rich at argonauta.org>
 * @version    SVN: $Id: sfDoctrineFormPluginTemplate.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
abstract class Plugin_4bPaymentDataForm extends Base_4bPaymentDataForm
{
  public function configure()
  {
    parent::configure();

    $this->useFields(array());
  }
}

