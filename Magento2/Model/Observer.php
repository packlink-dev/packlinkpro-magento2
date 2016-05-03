<?php
/**
 * Copyright 2016 Packlink
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Packlink\Magento2\Model;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as FrameworkObserver;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\AppInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

require_once(BP . '/lib/Packlink/Shipment.php');
require_once(BP . '/lib/Packlink/Postal.php');
require_once(BP . '/lib/Packlink/Data/Shipment.php');

use Packlink\Data;
use Packlink\Postal;
use Packlink\Shipment;

class Observer implements ObserverInterface {
	/** @var Packlink\Magento2\Model\Configuration */
	private $cfg;
	private $messageManager;

	/**
	* Packlink_Magento1_Model_Observer constructor.
	*/
	public function __construct (ManagerInterface $messageManager) {
		$objectManager = ObjectManager::getInstance();
		$this->cfg = $objectManager->get('Packlink\Magento2\Model\Configuration');
		$this->messageManager = $messageManager;
	}

	private function log($msg, $level = Logger::DEBUG) {
		$writer = new Stream(BP . '/var/log/' . $this->cfg->getLogFileName());
		$logger = new Logger();
		$logger->addWriter($writer);
		$logger->debug($msg);
	}

	public function execute(FrameworkObserver $observer) {
		if(!$this->cfg->isEnabled()) {
			return;
		}

		/** @var Mage_Sales_Model_Order_Shipment $shipment */
		$shipment = $observer->getEvent()->getShipment();

		if($shipment->getData(__METHOD__)) {
			return;
		}
		$shipment->setData(__METHOD__, true);
		$objectManager = ObjectManager::getInstance();

		$this->log("trying to send shipment {$shipment->getIncrementId()}");

		$apiUrl = $this->cfg->getApiUrl();
		$accessToken = $this->cfg->getAccessToken();
		$sender = $this->cfg->getSender();

		$statusModel = $objectManager->get('Packlink\Magento2\Model\Shipment\Status');

		$address = $shipment->getShippingAddress();

		$regionId = $sender['city'];
		if(($sender['region_id'] !== '') && is_numeric($sender['region_id'])) {
			/** @var Mage_Directory_Model_Region $region */
			$region = $objectManager->get('Magento\Directory\Model\Region')->load($sender['region_id']);
			if($region && !$region->isObjectNew()) {
				$regionId = $region->getName();
			}
		}
		$sender['region_id'] = $regionId;

		$total = 0;
		$itemName = '';

		/** @var Mage_Sales_Model_Order_Shipment_Item $item */
		foreach($shipment->getAllItems() as $item) {
			if($item->getOrderItem()->getParentItem()) {
				continue;
			}
			if($itemName === '') {
				$itemName = $item->getOrderItem()->getName();
			}
			$total += $this->getItemRowTotal($item);
		}

		$shippingAmount = $shipment->getOrder()->getShippingAmount();
		$total += $shippingAmount;

		$fromPostal = new Postal(
			$apiUrl,
			$accessToken,
			$sender['country_id'],
			$sender['zip']
		);
		$toPostal = new Postal(
			$apiUrl,
			$accessToken,
			$address->getData('country_id'),
			$address->getData('postcode')
		);

		$package = new Data\Package();

		$this->log("{$shipment->getIncrementId()} Package: " . json_encode($package));

		$additionalData = new Data\Additional();
		$additionalData->postalZoneIdFrom = $fromPostal->getPostalZoneId();
		$additionalData->postalZoneIdTo = $toPostal->getPostalZoneId();
		$additionalData->postalZoneNameFrom = $fromPostal->getPostalZoneName();
		$additionalData->postalZoneNameTo = $toPostal->getPostalZoneName();
		$additionalData->zipCodeIdFrom = $fromPostal->getZipCodeId();
		$additionalData->zipCodeIdTo = $toPostal->getZipCodeId();
		$additionalData->shippingServiceName = $shipment->getOrder()->getShippingDescription();
		$additionalData->shippingServiceSelected = $shipment->getOrder()->getShippingMethod();
		$this->log("{$shipment->getIncrementId()} Additional: " . json_encode($additionalData));

		$adrFrom = new Data\Address();
		$adrFrom->country = $sender['country_id'];
		$adrFrom->city = $sender['city'];
		$adrFrom->state = $sender['region_id'];
		$adrFrom->zipCode = $sender['zip'];
		$adrFrom->zip = $sender['zip'];
		$adrFrom->lastName = $sender['last_name'];
		$adrFrom->firstName = $sender['first_name'];
		$adrFrom->street = $sender['address'];
		$adrFrom->email = $sender['email'];
		$adrFrom->phone = $sender['telephone'];
		$adrFrom->company = $sender['company'];
		$this->log("{$shipment->getIncrementId()} Address FROM: " . json_encode($adrFrom));

		$adrTo = new Data\Address();
		$adrTo->country = $address->getCountryId();
		$adrTo->city = $address->getCity();
		$adrTo->state = $address->getRegion();
		$adrTo->zipCode = $address->getPostcode();
		$adrTo->zip = $address->getPostcode();
		$adrTo->lastName = $address->getLastname();
		$adrTo->firstName = $address->getFirstname();
		$adrTo->street = $address->getStreetFull();
		$adrTo->email = $address->getEmail();
		$adrTo->phone = $address->getTelephone();
		$adrTo->company = $address->getCompany();
		$this->log("{$shipment->getIncrementId()} Address TO: " . json_encode($adrTo));


		$shipmentData = new Data\Shipment();
		$shipmentData->source = 'Magento (' . AppInterface::VERSION . ')';
		$shipmentData->insurance = new Data\Insurance();
		$shipmentData->additionalData = $additionalData;
		$shipmentData->contentValue = $total;
		$shipmentData->content = $itemName;
		$shipmentData->from = $adrFrom;
		$shipmentData->packages = array($package);
		$shipmentData->to = $adrTo;
		$this->log("{$shipment->getIncrementId()} Shipment: " . json_encode($shipmentData));

		$packlinkShipment = new Shipment(
			$apiUrl,
			$accessToken,
			$shipmentData
		);

		// never let error "escape" so we won't prevent shipping creation.
		// XXX do we really want to go ahead and create shipping if we where unable to send it?
		try {
			$reference = $packlinkShipment->send();

			$statusModel->markAsProcessed($shipment->getId(), 200, $reference);
			$message = __('Exported to Packlink Pro.');
			$this->messageManager->addSuccess($message);
			$shipment->addComment("{$message}\nReference #{$reference}", false, false);

			$this->log("{$shipment->getIncrementId()}: Order draft created, reference {$reference}");
		} catch(\Exception $e) {
			$statusModel->markAsError($shipment->getId(), $e->getCode(), $e->getMessage());
			$message = __('Encountered error when sending to Packlink Pro: %s', $e->getMessage());
			$this->messageManager->addError($message);
			$shipment->addComment($message, false, false);

			$this->log("{$shipment->getIncrementId()}: Got error while sending shipment data.\n{$e}", Zend_Log::ERR);
		}

		$shipment->save();
	}

	/**
	* @param $item Mage_Sales_Model_Order_Shipment_Item
	*
	* @return float
	*/
	private function getItemRowTotal($item) {
		$shipmentQty = $item->getQty();
		$orderedQty = $item->getOrderItem()->getQtyOrdered();

		$rowTaxAmount = $item->getOrderItem()->getTaxAmount();
		$rowTotal = $item->getOrderItem()->getRowTotal();
		$discountAmount = $item->getOrderItem()->getDiscountAmount();

		$rowTotalInclTax = $rowTotal + $rowTaxAmount;

		$discountPerOne = $discountAmount / $orderedQty;

		return ((($rowTotalInclTax / $orderedQty) * $shipmentQty) - ($discountPerOne * $shipmentQty));
	}
}
