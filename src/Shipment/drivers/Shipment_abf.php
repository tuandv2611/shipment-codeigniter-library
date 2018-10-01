<?php
use GuzzleHttp\Exception\BadResponseException;

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 *  @author :
 *  date    : 08 Apr, 2017
 *  Easy Shipment with Abf
 *  version: 1.0
 */

/**
    $post_data = [
        'DL'=>'2',
        'ID'=>'VXTCLGW2',
        'ShipCity'=>'Dallas',
        'ShipState'=>'TX',
        'ShipZip'=>'75201',
        'ShipCountry'=>'US',
        'ConsCity'=>'Tulsa',
        'ConsState'=>'OK',
        'ConsZip'=>'74104',
        'ConsCountry'=>'US',
        'Wgt1'=>'400',
        'Class1'=>'50.0',
        'ShipAff'=>'Y',
        'ShipMonth'=>'6',
        'ShipDay'=>'23',
        'ShipYear'=>'2017',
    ];

    $options['detail'] = [
        [
            'weight' => '400',
            'freight_class' => '50.0',
            'frt_lng' => '50',
            'frt_wdth' => '10',
            'frt_hght' => '2',
            'qty' => '1',
            'unit_type' => 'CRT',
        ],
        [
            'weight' => '599',
            'freight_class' => '60', // https://www.abfs.com/ecommerce/tlinks/tl_classes1.htm
            'frt_lng' => '22',
            'frt_wdth' => '11',
            'frt_hght' => '2',
            'qty' => '2',
            'unit_type' => 'CRB', // https://www.abfs.com/ecommerce/tlinks/tl_types1.htm
        ]
    ];

    Delivery options
    *************************************************
    Acc_CSD "Y" for Construction Site Delivery option.
    Acc_GRD_DEL "Y" for Liftgate-Ground Delivery option.
    Acc_IDEL    "Y" for Inside Delivery option.
    Acc_LAD "Y" for Limited Access Delivery option.
    LADType Type of Limited Access Delivery. Required for Acc_LAD:
        "C" for Church delivery.
        "M" for Military Site delivery.
        "S" for School delivery.
        "U" for Mini-Storage delivery.
        "O" for Other type of limited access delivery.
    Acc_RDEL    "Y" for Residential Delivery option.
    Acc_FLATBD  "Y" for Flatbed Delivery option.
    Acc_TRDSHWD "Y" for Tradeshow Delivery option.
    ***********************************************

    $options['delivery']= []
*/
class Shipment_abf extends CI_Driver
{
    /**
     * Stores shipping object
     *
     * @var object
     */
    public $shipping = null;

    // country
    public $country = 'US';

    // format default
    protected $post_data = [];
    // format default
    protected $format = 'xml';

    // format default
    protected $version = '1.0';

    // format default
    protected $allowed_methods = ['aquotexml', 'tracexml', 'bolxml', 'pickupxml'];

    // format default
    protected $default_method = 'aquotexml';

    // method_name
    protected $method_name = '';

    // errors
    protected $errors = [];

    // url request
    //aquotexml.asp?
    private $url = 'https://www.abfs.com/xml/%s.asp?';

    // construct
    public function __construct($option = array())
    {
        $this->shipping = new stdClass();
    }

    public function request()
    {
        // log_message('debug', print_r('ABF REQUEST', 1));
        // log_message('debug', print_r(json_encode($this->post_data), 1));
        $url = sprintf($this->url, $this->method_name);

        $this->shipping->response_xml = self::guzzle($url, $this->post_data);

        if ($this->shipping->response_xml) {
            return self::output();
        }

        return false;
    }

    public function output()
    {
        $response = simplexml_load_string($this->shipping->response_xml);

        if (isset($response->NUMERRORS) && (int) $response->NUMERRORS > 0) {
            if (is_object($response->ERROR) and is_object($response->ERROR->ERRORMESSAGE))
            {
                foreach($response->ERROR->ERRORMESSAGE as $error)
                {
                    $error = (string) $error;
                    // log_message('debug','ABF error');
                    // log_message('debug', print_r($error,1));
                    self::set_errors($error);
                }
            }
            return false;
        }

        $rate = new stdClass();
        if (isset($response->QUOTEID) and $response->QUOTEID)
        {
            isset($response->QUOTEID) and $rate->quote_number = (string) $response->QUOTEID;
            isset($response->SHIPDATE) and $rate->quote_date = (string) $response->SHIPDATE;
            isset($response->ADVERTISEDDUEDATE) and $rate->due_date = (string) $response->ADVERTISEDDUEDATE;
            isset($response->CHARGE) and $rate->charge = (string) $response->CHARGE;

            if (!empty($rate)) {
                $this->shipping->rate = $rate;
                $CI = &get_instance();
                $CI->session->set_userdata('shipping_quote', json_encode((array) $this->shipping));

                return true;
            }

        }

        return false;
    }

    public function guzzle($url, $data)
    {
        $client = new GuzzleHttp\Client;
        try{
            $response = $client->request('POST', $url, [
                'query' => $data,
                // 'verify' => false,
            ]);
        }catch(Exception $e){
            // log_message('debug','error');
            // log_message('debug',print_r($e,1));
            return;
        }
        return $response->getBody()->getContents();
    }

    public function accept_method($name = '')
    {
        //XMLwriter to write XML from scratch - Simple example
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

    public function set_delivery_type($types = [])
    {
        if (is_array($types) and count($types))
        {
            foreach ($types as $code => $type)
            {
                $this->post_data[$code] = $type;
            }
        }

        return $this;
    }

    // Set credentail to login api
    public function set_credential($credential)
    {
        if (is_array($credential)) $credential = (object) $credential;

        $this->post_data['ID'] = $credential->access_key;
        $this->post_data['DL'] = '2';

        return $this;
    }

    // Set bill term
    public function set_bill_term($bill = '')
    {
        return $this;
    }

    // Set shipper infomation
    public function set_shipper_info($shipper)
    {
        if (is_array($shipper)) $shipper = (object) $shipper;

        $this->post_data['ShipCity'] = $shipper->city;
        $this->post_data['ShipState'] = $shipper->state;
        $this->post_data['ShipZip'] = $shipper->zip;
        $this->post_data['ShipCountry'] = $this->country;

        return $this;
    }

    // Set consignee information
    public function set_consignee_info($consignee)
    {
        if (is_array($consignee)) $consignee = (object) $consignee;

        $this->post_data['ConsCity'] = $consignee->ship_city;
        $this->post_data['ConsState'] = $consignee->ship_state;
        $this->post_data['ConsZip'] = $consignee->ship_zip;
        $this->post_data['ConsCountry'] = $this->country;

        return $this;
    }

    public function set_pakage($items)
    {
        if (is_array($items) || is_object($items))
        {

            $item_num = 1;
            foreach ($items as $index => $item)
            {
                if (!is_object($item)) {
                    $item = (object) $item;
                }

                for ($x=0;$x<$item->qty;$x++) {

                    $weight_in_pounds = $item->weight;
                    if ($item->weight_unit == 'oz') {
                        $weight_in_pounds = $weight_in_pounds / 16;
                    }
                    $this->post_data["Wgt{$item_num}"] = $weight_in_pounds;
                    $this->post_data["Class{$item_num}"] = $item->ship_class;
                    $this->post_data["FrtLng{$item_num}"] = $item->length;
                    $this->post_data["FrtWdth{$item_num}"] = $item->width;
                    $this->post_data["FrtHght{$item_num}"] = $item->height;
                    $this->post_data["UnitNo{$item_num}"] = 1;
                    $this->post_data["UnitType{$item_num}"] = 'BX';

                    $item_num++;
                }
            }
        }

        return $this;
    }

    public function set_other_options($options = null)
    {
        if (!is_object($options)) $options = (object) $options;

        $this->post_data['ShipAff']     = 'Y';
        $this->post_data['FrtLWHType']  = 'IN';

        $date = new DateTime("now", new DateTimeZone('America/New_York') );

        $options->ship_date = $date->format('Y/m/d');

        if (strtotime($options->ship_date))
        {
            $this->post_data['ShipMonth']   = date('m', strtotime($options->ship_date));
            $this->post_data['ShipDay']     = date('d', strtotime($options->ship_date));
            $this->post_data['ShipYear']    = date('Y', strtotime($options->ship_date));
        }

        return $this;
    }

    public function request_pickup($data)
    {
        // log_message('debug','request_pickup');
        // log_message('debug', print_r($data,1));

        $request['ID'] = $data->api_info->access_key;
        // "1" for Shipper.
        // "2" for Consignee.
        // "3" for Third Party.
        $request['RequesterType'] = 1;
        // $request['Test'] = "Y";

        // "P" for prepaid.
        // "C" for collect.
        $request['PayTerms'] = "P";
        $request['ShipContact'] = $data->shipper_info->address;
        $request['ShipName'] = $data->shipper_info->address;
        $request['ShipAddress'] = $data->shipper_info->address;
        $request['ShipCity'] = $data->shipper_info->city;
        $request['ShipState'] = $data->shipper_info->state;
        $request['ShipZip'] = $data->shipper_info->zip;
        $request['ShipCountry'] = $data->shipper_info->country;
        $request['ShipPhone'] = $data->shipper_info->phone;

        $request['ConsCity'] = $data->order->order_info->ship_city;
        $request['ConsState'] = $data->order->order_info->ship_state;
        $request['ConsZip'] = $data->order->order_info->ship_zip;
        $request['ConsCountry'] = $data->order->order_info->ship_country;

        // (MM/DD/YYYY).

        $time = $data->order->start_pickup_time;
        $request['PickupDate'] = date('n/t/Y', strtotime($data->other_options['ship_date']));
        $request['AT'] = substr($time, 0, 2).':'.substr($time, -2);


        $url = sprintf($this->url, $data->method_name);

        $pickup_response = simplexml_load_string(self::guzzle($url, $request));

        log_message('debug', print_r($pickup_response,1));

        if (isset($pickup_response->NUMERRORS) and (int) $pickup_response->NUMERRORS > 0) {
            if (is_object($response->ERROR) and is_object($response->ERROR->ERRORMESSAGE))
            {
                foreach($response->ERROR->ERRORMESSAGE as $error)
                {
                    $error = (string) $error;
                    // log_message('debug','ABF error');
                    // log_message('debug', print_r($error,1));
                    self::set_errors($error);
                }
            }
            return false;
        }

        if (isset($pickup_response->CONFIRMATION)) {

            $pickup_info = new stdClass();
            isset($pickup_response->CONFIRMATION) and $pickup_info->pickup_id = (string) $pickup_response->CONFIRMATION;

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
