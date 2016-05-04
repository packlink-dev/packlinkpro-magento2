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
    private $objectManager;
    private $apiUrl;
    private $accessToken;

    /**
    * Packlink_Magento1_Model_Observer constructor.
    */
    public function __construct (ManagerInterface $messageManager) {
        $this->objectManager = ObjectManager::getInstance();
        $this->cfg = $this->objectManager->get('Packlink\Magento2\Model\Configuration');

        $this->apiUrl = $this->cfg->getApiUrl();
        $this->accessToken = $this->cfg->getAccessToken();

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
        $this->log("trying to send shipment {$shipment->getIncrementId()}");

        $sender = $this->getSender();

        $total = $this->getTotalAmount($shipment->getAllItems(), $shipment);

        $itemName = $this->getItemName($shipment->getAllItems());

        $fromPostal = $this->getPostal($sender['country_id'], $sender['zip']);

        $address = $shipment->getShippingAddress();
        $toPostal = $this->getPostal($address->getData('country_id'), $address->getData('postcode'));

        $package = new Data\Package();
        $this->log("{$shipment->getIncrementId()} Package: " . json_encode($package));

        $additionalData = $this->getAditionalData($fromPostal, $toPostal, $shipment);
        $this->log("{$shipment->getIncrementId()} Additional: " . json_encode($additionalData));

        $adrFrom = $this->getAddressFromArray($sender);
        $this->log("{$shipment->getIncrementId()} Address FROM: " . json_encode($adrFrom));

        $adrTo = $this->getAddressFromObject($address);
        $this->log("{$shipment->getIncrementId()} Address TO: " . json_encode($adrTo));

        $shipmentData = $this->getDataShipment($additionalData, $total, $itemName, array($package), $adrFrom, $adrTo);
        $this->log("{$shipment->getIncrementId()} Shipment: " . json_encode($shipmentData));

        $packlinkShipment = $this->getShipment($shipmentData);

        $processedShipment = $this->getProcessedShipment($packlinkShipment, $shipment);
        $processedShipment->save();
    }

    private function getSender()
    {
        $sender = $this->cfg->getSender();
        $sender['region_id'] = $this->getFinalRegionId($sender['region_id'], $sender['city']);

        return $sender;
    }

    private function getFinalRegionId($region_id, $default)
    {
        $regionId = $default;
        if(($region_id !== '') && is_numeric($region_id)) {
            /** @var Mage_Directory_Model_Region $region */
            $region = $this->objectManager->get('Magento\Directory\Model\Region')->load($region_id);
            if($region && !$region->isObjectNew()) {
                $regionId = $region->getName();
            }
        }

        return $regionId;
    }

    private function getTotalAmount(Array $items, $shipment)
    {
        $total = 0;

        /** @var Mage_Sales_Model_Order_Shipment_Item $item */
        foreach($items as $item) {
            if($item->getOrderItem()->getParentItem()) {
                continue;
            }

            $total += $this->getItemRowTotal($item);
        }

        $shippingAmount = $shipment->getOrder()->getShippingAmount();

        return $total + $shippingAmount ;
    }

    private function getItemName(Array $items)
    {
        $itemName = '';
        $i = 0;
        $numItems = count($items);

        while ($itemName === '' && $i < $numItems) {
            $itemName = $items[$i]->getOrderItem()->getName();
            $i++;
        }

        return $itemName;
    }

    private function getPostal($countryId, $postalCode)
    {
        return new Postal(
            $this->apiUrl,
            $this->accessToken,
            $countryId,
            $postalCode
        );
    }

    private function getAditionalData($fromPostal, $toPostal, $shipment)
    {
        $additionalData = new Data\Additional();
        $additionalData->postalZoneIdFrom = $fromPostal->getPostalZoneId();
        $additionalData->postalZoneIdTo = $toPostal->getPostalZoneId();
        $additionalData->postalZoneNameFrom = $fromPostal->getPostalZoneName();
        $additionalData->postalZoneNameTo = $toPostal->getPostalZoneName();
        $additionalData->zipCodeIdFrom = $fromPostal->getZipCodeId();
        $additionalData->zipCodeIdTo = $toPostal->getZipCodeId();
        $additionalData->shippingServiceName = $shipment->getOrder()->getShippingDescription();
        $additionalData->shippingServiceSelected = $shipment->getOrder()->getShippingMethod();


        return $additionalData;
    }

    private function getAddressFromArray(array $addressData)
    {
        $adr = new Data\Address();
        $adr->country = $addressData['country_id'];
        $adr->city = $addressData['city'];
        $adr->state = $addressData['region_id'];
        $adr->zipCode = $addressData['zip'];
        $adr->zip = $addressData['zip'];
        $adr->lastName = $addressData['last_name'];
        $adr->firstName = $addressData['first_name'];
        $adr->street = $addressData['address'];
        $adr->email = $addressData['email'];
        $adr->phone = $addressData['telephone'];
        $adr->company = $addressData['company'];

        return $adr;
    }

    private function getAddressFromObject(Mage_Sales_Model_Order_Shipment_Item $addressData)
    {
        $adr = new Data\Address();
        $adr->country = $addressData->getCountryId();
        $adr->city = $addressData->getCity();
        $adr->state = $addressData->getRegion();
        $adr->zipCode = $addressData->getPostcode();
        $adr->zip = $addressData->getPostcode();
        $adr->lastName = $addressData->getLastname();
        $adr->firstName = $addressData->getFirstname();
        $adr->street = $addressData->getStreetFull();
        $adr->email = $addressData->getEmail();
        $adr->phone = $addressData->getTelephone();
        $adr->company = $addressData->getCompany();

        return $adr;
    }

    private function getDataShipment($additionalData, $total, $itemName, $packages, $adrFrom, $adrTo)
    {
        $shipmentData = new Data\Shipment();
        $shipmentData->source = 'Magento (' . AppInterface::VERSION . ')';
        $shipmentData->insurance = new Data\Insurance();
        $shipmentData->additionalData = $additionalData;
        $shipmentData->contentValue = $total;
        $shipmentData->content = $itemName;
        $shipmentData->from = $adrFrom;
        $shipmentData->packages = $packages;
        $shipmentData->to = $adrTo;

        return $shipmentData;
    }

    private function getShipment($shipmentData)
    {
        return new Shipment(
            $this->apiUrl,
            $this->accessToken,
            $shipmentData
        );
    }

    private function getProcessedShipment($packlinkShipment, $shipment)
    {
        $statusModel = $this->objectManager->get('Packlink\Magento2\Model\Shipment\Status');

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

        return $shipment;
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
