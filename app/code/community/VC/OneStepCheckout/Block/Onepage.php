<?php   
class VC_OneStepCheckout_Block_Onepage extends Mage_Checkout_Block_Onepage{ 
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

	public function isLoggedIn() {
		return Mage::getSingleton('customer/session')->isLoggedIn();	
	}
}