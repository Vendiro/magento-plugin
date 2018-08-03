<?php

class Vendiro_ApiHandler_Model_Tracking extends Mage_Core_Model_Abstract
{
    /**
     * @var Vendiro_ApiHandler_Helper_Data
     */
    protected $helper;

    /** @var Mage_Core_Model_Resource */
    protected $resource;

    /**
     *
     */
    public function _construct()
    {
        parent::_construct();

        $this->helper = Mage::helper('vendiro_apihandler');
        $this->resource = Mage::getSingleton('core/resource');
    }

    /**
     * Mark Shipment As Sent
     *
     * @param string|int $shipmentId
     * @return void
     */
    protected function markShipmentAsSent($shipmentId)
    {
        if (empty($shipmentId)) {
            return;
        }

        $data = array(
            'vendiro_exported' => 1,
        );
        $condition = $this->resource->getConnection('core_read')->quoteInto('entity_id=?', $shipmentId);

        $this->resource->getConnection('core_write')->update(
            $this->resource->getTableName('sales_flat_shipment'),
            $data,
            $condition
        );
    }


    /**
     * Get carriers from Vendiro API
     *
     * @return array
     * @see https://api.vendiro.nl/docs/#get-carriers
     */
    public function getCarriersFromApi()
    {

        try {
            $apiData = $this->helper->getApiData();

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $apiData['url'] . '/client/carriers',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "Authorization: Basic " . base64_encode($apiData['key'].':'.$apiData['token']),
                    "Cache-Control: no-cache",
                    "Content-Type: application/json;"
                ],
            ]);

            $response = curl_exec($curl);
            $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err || $httpStatus != 200) {
                throw new Exception('Error by getting carriers data from Vendiro API: '.$err);
            } else {
                $carriers = json_decode($response);
                return $carriers;
            }

        } catch (Exception $e) {
            Mage::logException($e);
        }

        return [];
    }

    /**
     * Export Shipment to Vendiro API
     *
     * @see https://api.vendiro.nl/docs/#confirm-shipment
     */
    public function exportShipmentToApi()
    {
        /** @var Mage_Sales_Model_Resource_Order_Shipment_Collection $collection */
        $collection = Mage::getResourceModel('sales/order_shipment_collection');
        $collection->addFieldToFilter('vendiro_exported', 0);

        $select = $collection->getSelect();
        $select->joinInner(
            ['ord' => $this->resource->getTableName('sales_flat_order')],
            "main_table.order_id = ord.entity_id AND ord.vendiro_id != ''",
            ['vendiro_id' => 'ord.vendiro_id', 'shipping_method' => 'ord.vendiro_id']
        );

        /** @var Mage_Sales_Model_Order_Shipment $item */
        foreach ($collection as $item) {

            try {
                $apiData = $this->helper->getApiData();

                /** @var Mage_Sales_Model_Order_Shipment_Track $track */
                $trackCollection = $item->getTracksCollection();

                foreach ($trackCollection as $track) {
                    $data = [
                        'carrier_id' => $this->helper->getVendiroCarrierFromMagentoShippingMethod($track->getData('carrier_code')),
                        'shipment_code' => $track->getNumber()
                    ];
                }

                if (!isset($data)) {
                    continue;
                }

                $dataJson = json_encode($data);

                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_URL => $apiData['url'] . '/client/orders/'.$item->getData('vendiro_id').'/shipment',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "PUT",
                    CURLOPT_HTTPHEADER => [
                        "Accept: application/json",
                        "Authorization: Basic ". base64_encode($apiData['key'].':'.$apiData['token']),
                        "Cache-Control: no-cache",
                        "Content-Type: application/json; charset=UTF-8",
                        "Content-Length: " . strlen($dataJson)
                    ],
                ]);

                curl_setopt($curl, CURLOPT_POSTFIELDS, $dataJson);

                curl_exec($curl);
                $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                $err = curl_error($curl);

                curl_close($curl);

                if ($err) {
                    throw new Exception('Error by sending shipment data to Vendiro API: ' . (isset($err) ? $err : 'Status '.$httpStatus));
                }

                if ($httpStatus != 204 && $httpStatus != 422) {
                    throw new Exception('Error by sending shipment data to Vendiro API: ' . (isset($err) ? $err : 'Status '.$httpStatus));
                }

                $this->markShipmentAsSent($item->getId());
            } catch (Exception $e) {
                Mage::logException($e);
            }

        }
    }
}