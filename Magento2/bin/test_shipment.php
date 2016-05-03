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

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/../../../../../app/bootstrap.php';
$params = $_SERVER;
$params[Bootstrap::PARAM_REQUIRE_MAINTENANCE] = true; // default false
$params[Bootstrap::PARAM_REQUIRE_IS_INSTALLED] = false; // default true
$bootstrap = Bootstrap::create(BP, $params);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

$shipmentId = 6;

$shipment = $objectManager->get('Magento\Sales\Model\Order\Shipment')->load($shipmentId);

$objectManager->get('Packlink\Magento2\Model\Observer')->sendOrderToPacklink($shipment);
