<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 *  @author :
 *  date    : 08 Apr, 2017
 *  Easy Shipment with Daylight
 *  version: 1.0
 */
use GuzzleHttp\Exception\BadResponseException;

class Shipment_dylt extends CI_Driver
{
    /**
     * Stores shipping object
     *
     * @var object
     */
    public $shipping            = null;


    // service type
    public $service_type        = 'LTL';

    // service type
    public $accessorials        = ['Delivery']; // ['Pickup', 'Delivery', 'Other']
    /**
     * Stores xml object
     *
     * @var xml
     */
    protected $xml              = null;

    // format default
    protected $format           = 'xml';

    // format default
    protected $version          = '1.0';

    // format default
    protected $allowed_methods  = ['rateQuote', 'pickup'];

    // default method
    protected $default_method   = 'rateQuote';

    // method_name
    protected $method_name      = '';

    // errors
    protected $errors           = [];

    // url request
    private $url                = 'https://api.dylt.com/';

    private $oauth_url          = 'https://api.dylt.com/oauth/client_credential/accesstoken?grant_type=client_credentials';

    protected $post_data        = [];

    protected $oauth_data       = [];

    // construct
    public function __construct($option = array())
    {
        $this->shipping = new stdClass();
    }

    public function request()
    {
        $url = $this->url . $this->method_name;

        // log_message('debug', 'dylt url');
        // log_message('debug', print_r($url,1));

        $this->shipping->response = self::curl($url, $this->post_data);

        if ($this->shipping->response) {
            return self::output();
        }

        return false;
    }

    public function output()
    {
        // log_message('debug','dylt response');
        // log_message('debug',print_r($this->shipping->response,1));
        $response = $this->shipping->response;

        if (isset($response->dyltRateQuoteResp->success) && $response->dyltRateQuoteResp->success == "YES")
        {
            $rate = new stdClass();
            isset($response->dyltRateQuoteResp->quoteNumber) and $rate->quote_number = (string) $response->dyltRateQuoteResp->quoteNumber;
            isset($response->dyltRateQuoteResp->quoteDate) and $rate->quote_date = (string) $response->dyltRateQuoteResp->quoteDate;
            isset($response->dyltRateQuoteResp->totalWeight) and $rate->total_weight = (string) $response->dyltRateQuoteResp->totalWeight;
            isset($response->dyltRateQuoteResp->totalCharges->netCharge) and $rate->charge = (string) $response->dyltRateQuoteResp->totalCharges->netCharge;

            $this->shipping->rate = $rate;

            $CI = &get_instance();
            $CI->session->set_userdata('shipping_quote', json_encode((array) $this->shipping));

            return true;
        } else if (isset($response->dyltRateQuoteResp->success) && $response->dyltRateQuoteResp->success == "NO")
        {
            $error = (string) $response->dyltRateQuoteResp->errorInformation->errorMessage;
            self::set_errors($error);
        }
        return false;

    }

    public function curl($url, $data) {

        $json = json_encode($data);

        // log_message('debug','dylt request');
        // log_message('debug', print_r($data,1));

        if (self::get_token()) {
            if (isset($this->oauth_data->access_token)) {
                $authorization = "Authorization: Bearer " . $this->oauth_data->access_token;
                // log_message('debug', $authorization);
                $curl = curl_init($url);

                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

                $response = json_decode(curl_exec($curl));

                $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if(curl_error($curl) || $code != 200) {
                    log_message('debug','curl error');
                    log_message('debug', print_r($code,1));
                    log_message('debug', print_r($response,1));
                    log_message('debug', print_r(curl_error($curl),1));

                    $error = array(
                        'status' => 'error',
                        'message' => curl_error($curl)
                    );
                }

                curl_close($curl);

                return $response;

            } else {
                log_message('debug','no token');
                log_message('debug',print_r($this->oauth_data,1));
        }
        } else {
            return false;
        }
    }

    public function get_token()
    {
        $params = array(
            "grant_type" => "client_credentials",
            "client_secret" => "WDUaQ0I5bR0j64DU",
            "client_id" => "el0uL1smd2s40SGqntPWJORu30syiohm"
        );

        $curl = curl_init($this->oauth_url);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HEADER,'Content-Type: application/x-www-form-urlencoded');

        $postData = "";

        //This is needed to properly form post the credentials object
        foreach($params as $k => $v) {
            $postData .= $k . '='.urlencode($v).'&';
        }

        $postData = rtrim($postData, '&');

        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

        $oauth_data = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($status != 200) {
            log_message('debug','oauth error');
            log_message('debug', print_r($oath_data,1));
            return false;
        }
        $this->oauth_data = json_decode($oauth_data);

        return true;
    }

    public function accept_method($name = '')
    {
        if ('' == $name)
        {
            $name = $this->default_method;
        }

        $this->method_name = $name;
        if (!in_array($this->method_name, $this->allowed_methods))
        {
            $this->set_errors('Request is Denied!');
            return false;
        }

        return $this;
    }

    public function get_message()
    {
        $str_error = '';
        if (count($this->errors)) {
            foreach($this->errors as $error) {
                $str_error .= $error . '</br>';
            }
        }

        return $str_error;
    }

    public function set_errors($error)
    {
        if (!count($error))
        {
            return false;
        }

        if (is_string($error))
        {
            array_push($this->errors, $error);
        }

        if (is_array($error))
        {
            foreach ($error as $_error)
            {
                $this->set_errors($_error);
            }
        }
    }

    public function set_delivery_type($types)
    {
        if (is_array($types))
        {
            $accessorials = [];
            foreach ($types as $index => $type)
            {
                $accessorial = new stdClass();
                $accessorial->accName = current($this->accessorials);
                $accessorial->accId = $type;

                array_push($accessorials, $accessorial);
            }

            $this->post_data['dyltRateQuoteReq']['accessorials']['accessorial'] = $accessorials;

        return $this;
        }
    }

    // Set credentail to login api
    public function set_credential($credential)
    {
        if (is_array($credential)) $credential = (object) $credential;

        $this->post_data['dyltRateQuoteReq']['accountNumber'] = $credential->account_num;
        $this->post_data['dyltRateQuoteReq']['userName'] = $credential->username;
        $this->post_data['dyltRateQuoteReq']['password'] = $credential->password;

        return $this;
    }

    // Set credentail to login api
    public function set_bill_term($bill = 'Prepaid')
    {
        $this->post_data['billTerms'] = 'PP';
        return $this;
    }

    // Set shipper infomation
    public function set_shipper_info($shipper)
    {

        $shipperInfo = [
            'customerNumber' => $shipper->phone,
            'customerName' => $shipper->company_name,
            'customerAddress' => [
                'streetAddress' => $shipper->address,
                'aptAddress' => '',
                'city' => $shipper->city,
                'state' => $shipper->state,
                'zip' => $shipper->zip
            ],
        ];

        $this->post_data['dyltRateQuoteReq']['shipperInfo'] = $shipperInfo;

        return $this;
    }

    // Set consignee information
    public function set_consignee_info($consignee)
    {
        if (is_array($consignee)) $consignee = (object) $consignee;

        $consigneeInfo = [
            'customerNumber' => $consignee->customer_phone,
            'customerName' => $consignee->recipient_name,
            'customerAddress' => [
                'streetAddress' => $consignee->shipping_address,
                'aptAddress' => '',
                'city' => $consignee->ship_city,
                'state' => $consignee->ship_state,
                'zip' => $consignee->ship_zip
            ],
        ];

        $this->post_data['dyltRateQuoteReq']['consigneeInfo'] = $consigneeInfo;

        return $this;
    }

    public function set_pakage($items)
    {
        $new_items = [];

        if (is_array($items) || is_object($items))
        {
            foreach ($items as $index => $item)
            {
                if (!is_object($item)) {
                    $item = (object) $item;
                };
                for ($x = 0; $x < (int)$item->qty; $x++) {

                    $weight_in_pounds = $item->weight;
                    if ($item->weight_unit == 'oz') {
                        $weight_in_pounds = $weight_in_pounds / 16;
                    }

                    $new_item = array(
                        'description' => $item->name,
                        'nmfcNumber' => isset($item->nmfc_number) ? $item->nmfc_number : '',
                        'nmfcSubNumber' => isset($item->nmfc_subnumber) ? $item->nmfc_subnumber : '',
                        'pcs' => $item->item_per_package,
                        'weight' => $weight_in_pounds,
                        'actualClass' => $item->ship_class
                    );

                    array_push($new_items, $new_item);
                }
            }

        } else {
            log_message('debug','no items');
        }
        $this->post_data['dyltRateQuoteReq']['items']['item'] = $new_items;
        return $this;
    }

    public function set_other_options($options = null)
    {
        $this->post_data['dyltRateQuoteReq']['serviceType'] = 'LTL';
        return $this;
    }

    public function request_pickup($data)
    {
        // log_message('debug', 'request_pickup dylt');
        // log_message('debug', print_r($data,1));
        $request = array();

        $request['accountNumber'] = $data->api_info->account_num;
        $request['userName'] = $data->api_info->username;
        $request['password'] = $data->api_info->password;

        $request['shipmentID'] = $data->order->order_info->order_id;

        $request['billTerms'] = 'PP';
        $request['serviceType'] = 'LTL';

        $request['shipperName'] = $data->shipper_info->company_name;
        $request['shipperAddress1'] = $data->shipper_info->address;
        $request['shipperAddress2'] = $data->shipper_info->address2;
        $request['shipperCity'] = $data->shipper_info->city;
        $request['shipperState'] = $data->shipper_info->state;
        $request['shipperZip'] = $data->shipper_info->zip;
        $request['shipperContactName'] = $data->shipper_info->company_name;
        $request['shipperContactNumber'] = $data->shipper_info->phone;

        $request['consigneeName'] = $data->order->order_info->customer_name;
        $request['consigneeAddress1'] = $data->order->order_info->shipping_address;
        $request['consigneeAddress2'] = $data->order->order_info->ship_add2;
        $request['consigneeCity'] = $data->order->order_info->ship_city;
        $request['consigneeState'] = $data->order->order_info->ship_state;
        $request['consigneeZip'] = $data->order->order_info->ship_zip;
        $request['consigneeContactName'] = $data->order->order_info->customer_name;
        $request['consigneeContactNumber'] = $data->order->order_info->customer_phone;

        $time1 = $data->order->start_pickup_time;
        $request['pickupStartDate'] = date('Y-n-j', strtotime('now'));
        $request['pickupStartTime'] = substr($time1, 0, 2).':'.substr($time1, -2) .':00';

        $time2 = $data->order->end_pickup_time;
        $request['pickupEndDate'] = date('Y-n-j', strtotime('+ 7 days'));
        $request['pickupEndTime'] = substr($time2, 0, 2).':'.substr($time2, -2) .':00';

        $items = array();

        foreach ($data->items as $index => $item)
        {
            if (!is_object($item)) {
                $item = (object) $item;
            };
            for ($x = 0; $x < (int)$item->product_quantity; $x++) {
                $new_item = array(
                    'description' => $item->product_name,
                    'nmfcNumber' => isset($item->nmfc_number) ? $item->nmfc_number : '',
                    'nmfcSubNumber' => isset($item->nmfc_subnumber) ? $item->nmfc_subnumber : '',
                    'pcs' => $item->item_per_package,
                    'pallets' => '1',
                    'weight' => (float) $item->weight,
                    'actualClass' => $item->default_ship_class
                );

                array_push($items, $new_item);
            }
        }

        $request['items']['item'] = $items;


        $request['shipReferences'] = [
            'shipReference'=> '',
            'referenceNumber' => ''
        ];

        $request['notes'] = [
            'note' => $data->order->order_info->note
        ];

        $accessorials = [];
        foreach ($data->delivery_type as $index => $type)
        {
            $accessorial = new stdClass();
            $accessorial->accName = current($this->accessorials);
            $accessorial->accId = $type;

            array_push($accessorials, $accessorial);
        }

        $request['accessorials']['accessorial'] = $accessorials;

        $dyltPickupReq['dyltPickupReqs']['dyltPickupReq'] = $request;

        $url = $this->url . $data->method_name;

        $pickup_response = self::curl($url, $dyltPickupReq);

        if (isset($pickup_response->dyltPickupResps->dyltPickupResp->bookingNumber)) {

            $pickup_info = new stdClass();
            isset($pickup_response->dyltPickupResps->dyltPickupResp->bookingNumber) and $pickup_info->pickup_id = (string) $pickup_response->dyltPickupResps->dyltPickupResp->bookingNumber;


            $this->shipping->pickup_info = $pickup_info;
            $CI = &get_instance();

            $CI->session->set_userdata('pickup_info', json_encode((array) $this->shipping));

            return $this->shipping->pickup_info;
        }

        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * Is this caching driver supported on the system?
     * Of course this one is.
     *
     * @return TRUE;
     */
    public function is_supported()
    {
        return TRUE;
    }

}
