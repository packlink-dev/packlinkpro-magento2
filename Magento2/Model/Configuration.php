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

use \Magento\Framework\App\ObjectManager;
use \Magento\Store\Model\ScopeInterface;

final class Configuration {
    /**
    * @return string
    */
    public function getLogFileName() {
        return 'packlink.log';
    }

    /**
    * @return string
    */
    public function getApiUrl() {
        return $this->getStoreConfigValue('packlink_magento2/general/service_url');
    }

    /**
    * @return string
    */
    public function getAccessToken() {
        return $this->getStoreConfigValue('packlink_magento2/general/api_key');
    }

    /**
    * @return array
    */
    public function getSender() {
        return $this->getStoreConfigValue('packlink_magento2/sender');
    }

    /**
    * @return bool
    */
    public function isEnabled() {
        return $this->getStoreConfigValue('packlink_magento2/general/enabled');
    }

    protected function getStoreConfigValue($path) {
        $object_manager = ObjectManager::getInstance();
        $helper = $object_manager->get('\Packlink\Magento2\Helper\Data');
        $scope = ScopeInterface::SCOPE_STORE;
        return $helper->_scopeConfig->getValue($path, $scope);
    }
}
