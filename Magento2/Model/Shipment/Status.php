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

/**
 * @method setShipmentId(int $id)
 * @method int getShipmentId()
 * @method setShipmentStatus(int $id)
 * @method int getShipmentStatus()
 * @method setCreated(string $datetime)
 * @method string getCreated()
 * @method setModified(string $datetime)
 * @method string getModified()
 * @method setErrorMessage(string $msg)
 * @method string getErrorMessage()
 * @method setReference(string $ref)
 * @method string getReference()
 * @method setTracking(string $tracking)
 * @method string getTracking()
 */
namespace Packlink\Magento2\Model\Shipment;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\App\ObjectManager;

class Status extends AbstractModel {

    public function _construct() {
        parent::_construct();
        $this->_init('Packlink\Magento2\Model\Resource\Shipment\Status');
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
    * @param int $shipmentId
    * @param string $reference
    * @param int $status
    */
    public function markAsProcessed($shipmentId, $status, $reference) {
        $this->setStatus($shipmentId, $status, null, $reference);
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
    * @param int $shipmentId
    * @param int $status
    * @param string $message
    * @param string $reference
    * @param string $tracking
    */
    public function setStatus($shipmentId, $status, $message = '', $reference = '', $tracking = '') {
        /** @var Packlink_Magento1_Model_Shipment_Status $obj */
        $objectManager = ObjectManager::getInstance();
        $obj = $objectManager->get('Packlink\Magento2\Model\Shipment\Status')->load($shipmentId);

        if(!$obj || $obj->isObjectNew()) {
                $obj->setShipmentId($shipmentId);
                $obj->setCreated(date('c'));
        } else {
                $obj->setModified(date('c'));
        }

        $obj->setShipmentStatus($status);
        $obj->setErrorMessage($message);
        $obj->setReference($reference);
        $obj->setTracking($tracking);
        $obj->save();
    }

    /**
    * @param int $shipmentId
    * @param string $error
    * @param string $status
    */
    public function markAsError($shipmentId, $status, $error) {
        $this->setStatus($shipmentId, $status, $error);
    }
}
