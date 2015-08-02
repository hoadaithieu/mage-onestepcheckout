<?php
class VC_OneStepCheckout_Helper_Data extends Mage_Core_Helper_Abstract {

	public function getCheckoutUrl($args = array()) {
		$params = array('_secure' => true);
		return $this->_getUrl('vc_onestepcheckout', $params);
	}

	public function enable() {
		return Mage::getStoreConfig('vc_onestepcheckout/general/enable') == 1 ? true: false;
	}
}