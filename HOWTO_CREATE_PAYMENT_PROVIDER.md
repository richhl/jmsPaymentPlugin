1. Select the id/name of your provider and add it to modules/paymentDemo/lib/PaymentDemoForm.class.php
Example of adding 4b provider:
private static $paymentMethods = array(
	'MicropaymentDebit' => 'Micropayment Debit',
	'Paypal' => 'PayPal',
	->>> '4b' => '4b', <<<-
	);
2. Create the data model for your provider reflecting specific data needed for paying whith that provider method. Use another included provider data model ofr inspiration suitable form as template (for example config/doctrine/paypal.yml).
In that model, you have to reference the class implementing payment method for that provider.
Example of 4b provider payment method reference:
....
options:
  type:         InnoDB
  charset:      utf8
  collate:      utf8_unicode_ci

PaymentDataFor4b:
  inheritance:
    extends: PaymentData
    type: column_aggregation
    keyField: method_class_name
    keyValue: jms4bPaymentMethod
....
3. Autogenerate model and forms ./symfony doctrine:build-model; ./symfony doctrine:build-form
4. Check: at this point you are supposed to see a Method named 4b when launching the create payment form. If you select that method you are going to get a form with fields for the base payment.
5. Make the form show fields specific to your payment method (see PluginPaypalPaymentDataForm.class.php or PluginPaymentDataForm.class.php for inspiration). For example, edit plugins/jmsPaymentPlugin/lib/form/doctrine/Plugin_4bPaymentDataForm.class.php and add this to the class:
abstract class Plugin_4bPaymentDataForm extends Base_4bPaymentDataForm
{
  public function configure()
  {
    parent::configure();

    $this->useFields(array());
  }
}
4. Implement the class for the payment method referenced in number 2 of this list.

NOTES: Take into account this concepts for implementing the jmsPaymentMethod in step 4: 
1. If you are not going to call an API on payment service and you need the user to be redirect to payment service pages you have to launch this exception: new jmsPaymentUserActionRequiredException( new jmsPaymentUserActionVisitURL($url_you_want_the_user_to_visit)); 
2. If your payment system is going to communicate results via a web request (case of 4b for example, not using API but request-response model) you have to take this results in an action, update DataContainer of the payment and perform the transaction this communication refers to.
