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
namespace Packlink\Magento2\Setup;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class InstallSchema implements InstallSchemaInterface {
	public function install(SchemaSetupInterface $setup, ModuleContextInterface $context) {
		$setup->startSetup();
		$table = $setup->getConnection()->newTable(
					$setup->getTable('packlink_magento2_shipment_status')
				)->addColumn(
					'id',
					\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
					null,
					['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
					'ID'
				)->addColumn(
					'shipment_id',
					\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
					null,
					['nullable' => false, 'unsigned' => true],
					'Shipment ID'
				)->addForeignKey(
					$setup->getFkName('packlink_magento2_shipment_status', 'shipment_id', 'sales_shipment', 'entity_id'),
					'shipment_id',
					$setup->getTable('sales_shipment'),
					'entity_id',
					\Magento\Framework\DB\Ddl\Table::ACTION_CASCADE,
					\Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
				)->addColumn(
					'shipment_status',
					\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
					null,
					['nullable' => false, 'unsigned' => true, 'default' => 0,],
					'Shipment Status'
				)->addIndex(
					$setup->getIdxName(
						'packlink_magento2_shipment_status',
						['shipment_status']
					),
					['shipment_status'],
					['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX]
				)->addColumn(
					'created',
					\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
					null,
					['nullable' => false],
					'Created At'
				) ->addColumn(
					'modified',
					\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
					null,
					['nullable' => false],
					'Modified At'
				)->addColumn(
					'error_message',
					\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
					\Magento\Framework\DB\Ddl\Table::MAX_TEXT_SIZE,
					['nullable' => false],
					'Error Message'
				)->addColumn(
					'reference',
					\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
					\Magento\Framework\DB\Ddl\Table::MAX_TEXT_SIZE,
					['nullable' => false],
					'Reference'
				)->addColumn(
					'tracking',
					\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
					\Magento\Framework\DB\Ddl\Table::MAX_TEXT_SIZE,
					['nullable' => false],
					'Tracking'
				);
		$setup->getConnection()->createTable($table);
		$setup->endSetup();
	}
}
