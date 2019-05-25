<?php

class Vendiro_ApiHandler_Model_Stock extends Mage_Core_Model_Abstract
{
    /**
     * @var Vendiro_ApiHandler_Helper_Data
     */
    protected $helper;

    /** @var Mage_Core_Model_Resource */
    protected $resource;

    public function _construct()
    {
        parent::_construct();

        $this->helper = Mage::helper('vendiro_apihandler');
        $this->_init('vendiro_apihandler/stock');

        $this->resource = Mage::getSingleton('core/resource');
    }

    /**
     * Update all stock in Vendiro
     */
    public function updateAll()
    {
        $this->sendStockToApi(true);
    }

    /**
     * Clear queue table
     *
     */
    public function clearProductQueueTable()
    {
        $this->resource->getConnection('core_write')->truncateTable(
            $this->resource->getTableName('vendiro_product_stock_updated')
        );
    }

    /**
     * Adding products whose stock was updated to queue
     *
     * @param array $productIds
     * @return void
     */
    public function addProductToQueue($productIds)
    {
        if (empty($productIds)) {
            return;
        }

        $productIdsInQueue = $this->getProductIdsFromQueueTable();

        foreach ($productIds as $productId) {
            if (!in_array($productId, $productIdsInQueue)) {
                $data = [
                    'product_id' => (int)$productId
                ];

                try {
                    $this->resource->getConnection('core_write')->insertOnDuplicate(
                        $this->resource->getTableName('vendiro_product_stock_updated'),
                        $data,
                        ['product_id']
                    );
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        }
    }

    /**
     * Get Product Ids from queue table
     *
     * @return array
     */
    public function getProductIdsFromQueueTable()
    {
        $readAdapter = $this->resource->getConnection('core_read');
        $select = $readAdapter->select()
            ->from($this->resource->getTableName('vendiro_product_stock_updated'), 'product_id');

        $productIds = $readAdapter->fetchCol(
            $select
        );

        return $productIds;
    }

    /**
     * @param bool $allProducts
     * @return array
     * @throws Mage_Core_Exception
     */
    public function prepareProductsToSend($allProducts = false)
    {
        $data = [];
        $productIds = $this->getProductIdsFromQueueTable();

        if (!$allProducts && empty($productIds)) {
            return $data;
        }

        /** @var Mage_Catalog_Model_Product $modelProduct */
        $modelProduct = Mage::getSingleton('catalog/product');
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = $modelProduct->getCollection();
        $collection->setFlag('require_stock_items', true);

        if (!$allProducts) {
            $collection->addFieldToFilter('entity_id', ['in', $productIds]);
        }

        /** @var Mage_Catalog_Model_Product $product */
        foreach ($collection as $product) {
            if(!$product->getStockItem()) {
                continue;
            }

            $data[] = [
                'sku' => $product->getSku(),
                'stock' => (int) $product->getStockItem()->getQty()
            ];
        }

        return $data;
    }

    /**
     * Sending stock data to Vendiro API
     *
     * @param bool $allProducts
     * @return void
     * @see https://api.vendiro.nl/docs/#update-stock
     */
    public function sendStockToApi($allProducts = false)
    {

        try {
            $apiData = $this->helper->getApiData();

            $productData = $this->prepareProductsToSend($allProducts);

            if (empty($productData)) {
                return;
            }

            $productDataJson = json_encode($productData);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $apiData['url'] . '/client/products/stock',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "Authorization: Basic ". base64_encode($apiData['key'].':'.$apiData['token']),
                    "Cache-Control: no-cache",
                    "Content-Type: application/json; charset=UTF-8",
                    "Content-Length: " . strlen($productDataJson),
                    "User-Agent: VendiroMagentoPlugin/" . $this->helper->getModuleVersion()
                ],
            ]);

            curl_setopt($curl, CURLOPT_POSTFIELDS, $productDataJson);

            curl_exec($curl);
            $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err || $httpStatus != 201) {
                throw new Exception('Error by sending stock data to Vendiro API: ' . (isset($err) ? $err : 'Status '.$httpStatus));
            } else {
                $this->clearProductQueueTable();
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}