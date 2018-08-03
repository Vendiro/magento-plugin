<?php

/**
 * Class Vendiro_ApiHandler_Adminhtml_SelftestController
 */
class Vendiro_ApiHandler_Adminhtml_SelftestController extends Mage_Adminhtml_Controller_Action
{

    /**
     * @var
     */
    public $helper;

    /**
     * Construct.
     */
    public function _construct()
    {
        $this->helper = Mage::helper('vendiro_apihandler');
        parent::_construct();
    }

    /**
     *
     */
    public function runAction()
    {
        try {
            $this->apiData = $this->helper->getApiData();

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $this->apiData['url'] . '/client/account',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "Authorization: Basic " . base64_encode($this->apiData['key'].':'.$this->apiData['token']),
                    "Cache-Control: no-cache",
                    "Content-Type: application/json;"
                ],
            ]);

            $response = curl_exec($curl);
            $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err || $httpStatus != 200) {
                echo 'Error not able to login!';
            } else {
                $account = json_decode($response);
                echo 'Login successful ' . 'Account: ' . $account->account .' User: ' . $account->user;
            }

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    /**
     * @return mixed
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('admin/system/config/vendiro_apihandler');
    }
}