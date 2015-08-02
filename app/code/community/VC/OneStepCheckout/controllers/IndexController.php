<?php
include "app/code/core/Mage/Checkout/controllers/OnepageController.php";
class VC_OneStepCheckout_IndexController extends Mage_Checkout_OnepageController{
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }
	
    protected function _expireAjax()
    {
        if (!$this->getOnepage()->getQuote()->hasItems()
            || $this->getOnepage()->getQuote()->getHasError()
            || $this->getOnepage()->getQuote()->getIsMultiShipping()
        ) {
            $this->_ajaxRedirectResponse();
            return true;
        }
        $action = $this->getRequest()->getActionName();
        if (Mage::getSingleton('checkout/session')->getCartWasUpdated(true)
            && !in_array($action, array( 'index', 'saveBilling', 'saveShipping', 'saveShippingMethod','progress'))
        ) {
            $this->_ajaxRedirectResponse();
            return true;
        }
        return false;
    }
	
    public function indexAction() {
		$this->loadLayout();
		if (!Mage::helper('vc_onestepcheckout')->enable()) {
			$session = $this->_getSession();
			$session->addError($this->__('Please active OneStepCheckout extension!'));
		}
		
		$this->renderLayout(); 
    }
	
	public function loginPostAction() {
		$result = array();
		try {
			if (!$this->_validateFormKey()) {
				Mage::throwException($this->__('Invalid Form key.'));
			}
	
			$session = $this->_getSession();
	
			if ($this->getRequest()->isPost()) {
				$login = $this->getRequest()->getPost('login');
				if (!empty($login['username']) && !empty($login['password'])) {
					try {
						$session->login($login['username'], $login['password']);
						$result['redirect'] = Mage::helper('vc_onestepcheckout')->getCheckoutUrl();
					} catch (Mage_Core_Exception $e) {
						switch ($e->getCode()) {
							case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
								$value = $this->_getHelper('customer')->getEmailConfirmationUrl($login['username']);
								$message = $this->_getHelper('customer')->__('This account is not confirmed. <a href="%s">Click here</a> to resend confirmation email.', $value);
								break;
							case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
								$message = $e->getMessage();
								break;
							default:
								$message = $e->getMessage();
						}
						$session->addError($message);
						$session->setUsername($login['username']);
						Mage::throwException($e->getMessage()); 
					} catch (Exception $e) {
						Mage::throwException($e->getMessage()); 
					}
				} else {
					$session->addError($this->__('Login and password are required.'));
					Mage::throwException($this->__('Login and password are required.')); 
				}
			}
		} catch (Exception $e) {
			$result['error'] = 1;
			$result['message'] = $e->getMessage();
		}
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));	
	}
	
    /**
     * Save checkout billing address
     */
    public function saveBillingAction()
    {
        if ($this->_expireAjax()) {
            return;
        }
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('billing', array());
            $customerAddressId = $this->getRequest()->getPost('billing_address_id', false);

            if (isset($data['email'])) {
                $data['email'] = trim($data['email']);
            }
            $result = $this->getOnepage()->saveBilling($data, $customerAddressId);

            if (!isset($result['error'])) {
				$update = $this->getLayout()->getUpdate();
				$update->addHandle('vc_onestepcheckout_index_shippingmethod')
				->addHandle('vc_onestepcheckout_index_paymentmethod')
				->addHandle('vc_onestepcheckout_index_review');
				
				$this->loadLayoutUpdates();
				$this->generateLayoutXml()->generateLayoutBlocks();			
			
                if ($this->getOnepage()->getQuote()->isVirtual()) {
                    $result['goto_section'] = 'payment';
                    $result['update_section'] = array(
                        'name' => 'payment-method',
                        //'html' => $this->_getPaymentMethodsHtml()
						'payment_method_html' => $this->getLayout()->getBlock('vc_onestepcheckout.paymentmethod')->toHtml(),
						'review_html' => $this->getLayout()->getBlock('vc_onestepcheckout.review')->toHtml()
						
                    );
                } elseif (isset($data['use_for_shipping']) && $data['use_for_shipping'] == 1) {
                    $result['goto_section'] = 'shipping_method';
                    $result['update_section'] = array(
                        'name' => 'shipping-method',
                        //'html' => $this->_getShippingMethodsHtml()
						'shipping_method_html' => $this->getLayout()->getBlock('vc_onestepcheckout.shippingmethod')->toHtml(),
						'payment_method_html' => $this->getLayout()->getBlock('vc_onestepcheckout.paymentmethod')->toHtml(),
						'review_html' => $this->getLayout()->getBlock('vc_onestepcheckout.review')->toHtml()
                    );

                    $result['allow_sections'] = array('shipping');
                    $result['duplicateBillingInfo'] = 'true';
                } else {
                    $result['goto_section'] = 'shipping';
                }
            }

            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }

    /**
     * Shipping address save action
     */
    public function saveShippingAction()
    {
        if ($this->_expireAjax()) {
            return;
        }
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shipping', array());
            $customerAddressId = $this->getRequest()->getPost('shipping_address_id', false);
            $result = $this->getOnepage()->saveShipping($data, $customerAddressId);

            if (!isset($result['error'])) {
				$update = $this->getLayout()->getUpdate();
				$update->addHandle('vc_onestepcheckout_index_shippingmethod')
				->addHandle('vc_onestepcheckout_index_paymentmethod')
				->addHandle('vc_onestepcheckout_index_review');
				$this->loadLayoutUpdates();
				$this->generateLayoutXml()->generateLayoutBlocks();			
			
                $result['goto_section'] = 'shipping_method';
                $result['update_section'] = array(
                    'name' => 'shipping-method',
                    //'html' => $this->_getShippingMethodsHtml()
					'shipping_method_html' => $this->getLayout()->getBlock('vc_onestepcheckout.shippingmethod')->toHtml(),
					'payment_method_html' => $this->getLayout()->getBlock('vc_onestepcheckout.paymentmethod')->toHtml(),
					'review_html' => $this->getLayout()->getBlock('vc_onestepcheckout.review')->toHtml()
                );
            }
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }	
	
    public function saveShippingMethodAction()
    {
        if ($this->_expireAjax()) {
            return;
        }
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shipping_method', '');
            $result = $this->getOnepage()->saveShippingMethod($data);
            // $result will contain error data if shipping method is empty
            if (!$result) {
                Mage::dispatchEvent(
                    'checkout_controller_onepage_save_shipping_method',
                     array(
                          'request' => $this->getRequest(),
                          'quote'   => $this->getOnepage()->getQuote()));
                $this->getOnepage()->getQuote()->collectTotals();
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));

				$update = $this->getLayout()->getUpdate();
				$update->addHandle('vc_onestepcheckout_index_paymentmethod')
				->addHandle('vc_onestepcheckout_index_review');
				$this->loadLayoutUpdates();
				$this->generateLayoutXml()->generateLayoutBlocks();			

                $result['goto_section'] = 'payment';
                $result['update_section'] = array(
                    //'name' => 'payment-method',
                    //'html' => $this->_getPaymentMethodsHtml(),
					'payment_method_html' => $this->getLayout()->getBlock('vc_onestepcheckout.paymentmethod')->toHtml(),
					'review_html' => $this->getLayout()->getBlock('vc_onestepcheckout.review')->toHtml() 
                );
            }
            $this->getOnepage()->getQuote()->collectTotals()->save();
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }
	
 	public function saveOrderAction()
    {
        if (!$this->_validateFormKey()) {
            $this->_redirect('*/*');
            return;
        }

        if ($this->_expireAjax()) {
            return;
        }

        $result = array();
        try {
            $data = $this->getRequest()->getPost('shipping_method', '');
            $result = $this->getOnepage()->saveShippingMethod($data);
            // $result will contain error data if shipping method is empty
            if (!$result) {
                Mage::dispatchEvent(
                    'checkout_controller_onepage_save_shipping_method',
                     array(
                          'request' => $this->getRequest(),
                          'quote'   => $this->getOnepage()->getQuote()));
                $this->getOnepage()->getQuote()->collectTotals();

            }
            $this->getOnepage()->getQuote()->collectTotals()->save();	
			
				
		
            $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
            if ($requiredAgreements) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                $diff = array_diff($requiredAgreements, $postedAgreements);
                if ($diff) {
                    $result['success'] = false;
                    $result['error'] = true;
                    $result['error_messages'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                    return;
                }
            }

            $data = $this->getRequest()->getPost('payment', array());
            if ($data) {
                $data['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                    | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                    | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
                $this->getOnepage()->getQuote()->getPayment()->importData($data);
            }

            $this->getOnepage()->saveOrder();

            $redirectUrl = $this->getOnepage()->getCheckout()->getRedirectUrl();
            $result['success'] = true;
            $result['error']   = false;
        } catch (Mage_Payment_Model_Info_Exception $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $result['error_messages'] = $message;
            }
            $result['goto_section'] = 'payment';
            $result['update_section'] = array(
                'name' => 'payment-method',
                'html' => $this->_getPaymentMethodsHtml()
            );
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success'] = false;
            $result['error'] = true;
            $result['error_messages'] = $e->getMessage();

            $gotoSection = $this->getOnepage()->getCheckout()->getGotoSection();
            if ($gotoSection) {
                $result['goto_section'] = $gotoSection;
                $this->getOnepage()->getCheckout()->setGotoSection(null);
            }
            $updateSection = $this->getOnepage()->getCheckout()->getUpdateSection();
            if ($updateSection) {
                if (isset($this->_sectionUpdateFunctions[$updateSection])) {
                    $updateSectionFunction = $this->_sectionUpdateFunctions[$updateSection];
                    $result['update_section'] = array(
                        'name' => $updateSection,
                        'html' => $this->$updateSectionFunction()
                    );
                }
                $this->getOnepage()->getCheckout()->setUpdateSection(null);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success']  = false;
            $result['error']    = true;
            $result['error_messages'] = $this->__('There was an error processing your order. Please contact us or try again later.');
        }
        $this->getOnepage()->getQuote()->save();
        /**
         * when there is redirect to third party, we don't want to save order yet.
         * we will save the order in return action.
         */
        if (isset($redirectUrl)) {
            $result['redirect'] = $redirectUrl;
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }	
}