<?php
/**
 * Creating queue table for collecting product ids where stock is updated
 */

/* @var $installer Mage_Catalog_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

/** @var $connection Varien_Db_Adapter_Pdo_Mysql */
$connection = $installer->getConnection();

try {
    $table = $connection->newTable($installer->getTable('vendiro_product_stock_updated'))
        ->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ], 'Product Id');
    $installer->getConnection()->createTable($table);

    $installer->getConnection()
        ->addColumn($installer->getTable('sales/order'),'vendiro_id', array(
            'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
            'nullable'  => false,
            'length'    => 255,
            'after'     => null,
            'comment'   => 'Vendiro ID'
        ));

    $installer->getConnection()
        ->addColumn($installer->getTable('sales_flat_shipment'),'vendiro_exported', array(
            'type'      => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'nullable'  => false,
            'default'   => '0',
            'length'    => 1,
            'after'     => null,
            'comment'   => 'Exported to Vendiro API?'
        ));

    $tableName = $installer->getTable('sales/order');
    $indexNameToCreate = $installer->getIdxName($tableName, array('vendiro_id'));
    $connection->addIndex($tableName, $indexNameToCreate, array('vendiro_id'));
} catch (\Exception $e) {}

$installer->endSetup();