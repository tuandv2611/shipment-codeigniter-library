<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
use GuzzleHttp\Exception\BadResponseException;
/*******************************************************************************
 *  @author :
 *  date    : 24 Nov, 2017
 *  Easy Shipment with YRC Freight
 *  version: 1.0
 *
 *   A. Support
 *   USA and CANADA
 *   Any where not in there is Out of Net Work(ONET).
 *   Weight default is by pounds.
 *    B. Request Param
 *  1. Basic information
 *   Login params, Origien location was setup by account from yrc freight site
 *   Destination location: location will shippment item
 *
 *  2. Services Class For TRequest
 *   - serviceClass: STD , TCSA
 *  - typeQuery: MATRX, QUOTE. we use QUOTE
 *  - pickupDate: The scheduled date of pickup.  This variable is mandatory and is used to determine rates and service.  The expected format for the pickup date is as follows: CCYYMMDD
 *   - deliveryDate
 *  - currency
 *   - guarantee
 *
 *   3. Commodity items
 *   List of Comidities:
 *   breakDown, hazmatInd, poisonInd, heaviestPiece, stackable, tilt, totalCubicFeet, totalLinearFeet, totalWeight.

 *   Parameters represent the commodity
 *   handlingUnits, nmfcClass, packageCode, packageLength, packageWidth, packageHeight,
 *   weight, weightUom

 *  4. Services Type
 *   The YRC Freight Rate Quote web service expects the following values to designate the type of service used to determine rates and charges:
 *   All, STD, GDEL, ACEL, TCS, TCSA, TCSP, TCSW, FAF, DEGP
 *
 *   5. Accessorial service request
 *   - YRC Freight accept methods by service codes. By the way We only require some services in list.
 *   There are: Inside Delivery, Residential Pickup, Liftgate Service at Delivery.
 *   The default methos is: Notify before delivery.
 *   List of services codes that YRC Freight site support
 *
 *   Code Description
 *   LFTO Liftgate Service at Pickup.
 *   LFTD Liftgate Service at Delivery.
 *   HAZM Hazardous Materials.
 *   IP Inside Pickup.
 *   ID Inside Delivery.
 *   MARK Marking or Tagging.
 *   APPT Delivery Appointment.
 *   NTFY Notify before delivery.
 *   HOMP Residential Pickup.
 *   HOMD Residential Delivery.
 *   LTDO Limited Access Pickup.
 *   LTDD Limited Access Delivery.
 *   SHWO Tradeshow Pickup.
 *   SHWD Tradeshow Delivery.
 *   SS Single Shipment.
 *   PROA Proactive Notification
 *   FREZ Protect from Freezing.
 *******************************************************************************/
class Shipment_yrc extends CI_Driver
{
    /**
     * Stores shipping object
     *
     * @var object
     */
    public $shipping = null;

    const BUSINESS_ID = 333121232323;
    // format default
    protected $post_data = [
            'login' => [
                'busId' =>  Shipment_yrc::BUSINESS_ID,
                'busRole'   =>  'Shipper',
                'paymentTerms'  =>  'Prepaid'
            ],
            'details'   =>  [
                'serviceClass'  =>  'ALL',
                'typeQuery' =>  'QUOTE',
            ],
            "originLocation" =>  [
            ],
            "destinationLocation"=> [
            ],
            "listOfCommodities" => [
                "commodity" => [
                ]
            ],
            "serviceOpts" => [
                "accOptions" => [
                  "NTFY"
                ]
              ],
        ];

    // format default
    protected $version = '1.0';
    protected $default_method = 'qoute';
    protected $method_name = 'qoute';

    // errors
    protected $errors = [];

    protected $allowed_methods = ['qoute', 'matrx'];
    // url request
    //aquotexml.asp?
    private $url = 'https://api.yrc.com/node/api/ratequote';

    protected $country_code = [
        'US'    =>  'USA',
        'CA'    =>  'CAN'
    ];

    protected $delivery = [
        'Acc_GRD_DEL'   =>  'LFTD',
        'Acc_RDEL'  =>  'HOMD',
        'Acc_IDEL'  =>  'IP',
    ];

    protected $packagaCode = [
        'SKD'   =>  'SKD',
        'CTN'   =>  'CTN',
        'PLT'   =>  'PLT',
        'DR'    =>  'DRM'
    ];

    const SASC_CODE = 'YFSZ';
    // construct
    public function __construct($option = array())
    {
        $this->shipping = new stdClass();
        $this->method_name = 'qoute';
    }

    public function request()
    {
        $url = sprintf($this->url, $this->method_name);
        $query = $this->post_data;
        $client = new GuzzleHttp\Client;
        try{
            $response = $client->request('POST', $url, [
                'body' => json_encode($query),
                'headers' => ['Content-Type' => 'application/json'],
                // 'verify' => false,
            ]);
        }catch(Exception $e){
            return false;
        }
        $result = $this->shipping->response_json = $response->getBody()->getContents();
        // log_message('debug', print_r('YRC RESPONSE', 1));
        // log_message('debug', print_r(json_encode($this->shipping->response_json), 1));

        return $this->output();
    }

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

    public function set_delivery_type($delivery_type)
    {
        $this->post_data['serviceOpts'] = [];
        $options = ['NTFY'];
        foreach ($delivery_type as $key => $value) {
            $options[] = $value;
        }
        $this->post_data['serviceOpts']['accOptions'] = $options;
        return $this->post_data;
    }

    public function set_credential($credential)
    {
        if (is_array($credential)) $credential = (object) $credential;
        $this->post_data['login']['username'] = $credential->username;
        $this->post_data['login']['password'] = $credential->password;
        return $this->post_data;
    }

    public function set_bill_term($bill = 'Prepaid')
    {
        if (!isset($bill) || !$bill) {
            $bill = 'Prepaid';
        }
        $this->post_data['login']['paymentTerms'] = $bill;
        return $this;
    }

    public function set_shipper_info($shipper)
    {
        if (is_array($shipper)) $shipper = (object) $shipper;

        $this->post_data['originLocation']['city'] = $shipper->city;
        $this->post_data['originLocation']['state'] = $shipper->state;
        $this->post_data['originLocation']['postalCode'] = $shipper->zip;
        $this->post_data['originLocation']['country'] = $this->country_code[$shipper->country];

        return $this->post_data;
    }

    // Set consignee information
    public function set_consignee_info($consignee)
    {
        if (is_array($consignee)) $consignee = (object) $consignee;
        $this->post_data['destinationLocation']['city'] = $consignee->ship_city;
        $this->post_data['destinationLocation']['state'] = $consignee->ship_state;
        $this->post_data['destinationLocation']['postalCode'] = $consignee->ship_zip;
        $this->post_data['destinationLocation']['country'] = $this->country_code[$consignee->ship_country];

        return $this->post_data;
    }

    public function set_pakage($items)
    {
        if (is_array($items) || is_object($items))
        {
            foreach ($items as $index => $item)
            {
                if (!is_object($item)) {
                    $item = (object) $item;
                }
                $com = [];

                $weight_in_pounds = $item->weight;
                if ($item->$weight_unit == 'oz') {
                    $weight_in_pounds = $weight_in_pounds / 16;
                }

                $com['weight'] = $weight_in_pounds;
                $com['nmfcClass'] = $item->ship_class;
                $com['packageLength'] = $item->length;
                $com['packageWidth'] = $item->width;
                $com['packageHeight'] = $item->height;
                $com['handlingUnits'] = $item->qty;
                $com['packageCode'] = isset($this->packageCode[$item->unit_type]) ? $this->packageCode[$item->unit_type] : $this->packageCode['PLT'];
                $com['nmfc']  =  [
                    'item'  =>  $item->nmfc_number
                ];
                $this->post_data['listOfCommodities']['commodity'][] = $com;
            }
        }

        return $this->post_data;
    }

    public function set_other_options($options = null)
    {
        if (!is_object($options)) $options = (object) $options;


        $options->ship_date =isset($options->ship_date) ? $options->ship_date : date('Ymd');
        if (strtotime($options->ship_date))
        {
            $this->post_data['details']['pickupDate'] = date('Ymd', strtotime($options->ship_date));
        }

        return $this->post_data;
    }

    public function output()
    {
        $response = json_decode($this->shipping->response_json);
        if (! empty($response) && !$response->isSuccess) {
            // Error
            foreach ($response->errors as $key => $value) {
                $message = 'Error code ' . $value->field . ' : ' . $value->message;
                $this->set_errors($message);
            }
            return false;
        } else {
            $this->shipping->rates = [];
            //$response->pageRoot->bodyMain->rateQuote->referenceId for quote_number
            $rates = $response->pageRoot->bodyMain->rateQuote->quoteMatrix->table;
            $quote_number = $response->pageRoot->bodyMain->rateQuote->referenceId;
            $min_charge = PHP_INT_MAX;
            foreach ($rates as $value)
            {
                if ($value && isset($value->transitOptions)) {
                    foreach ($value->transitOptions as $key => $option) {
                        if (is_numeric($option->totalCharges) && $option->totalCharges < $min_charge) {
                            $rate = new stdClass();
                            $rate->TotalCharge = round($option->totalCharges / 100, 2);
                            $rate->CarrierSCAC = self::SASC_CODE;
                            $rate->quote_number = $quote_number;
                            array_push($this->shipping->rates, $rate);
                            $current_charge = $option->totalCharges;
                            $min_charge = $option->totalCharges;
                        }
                    }
                }
            }
            usort($this->shipping->rates, function($a, $b) {
                return $a->TotalCharge > $b->TotalCharge;
            });
            if (! empty($this->shipping->rates)) {
                $first = $this->shipping->rates[0];
                $this->shipping->rates = [];
                $this->shipping->rates[] = $first;

                // log_message('debug','Shipment by YRC');
                // log_message('debug',print_r($this->shipping->rates,1));

                $CI = &get_instance();
                $CI->session->set_userdata('shipping_quote', json_encode((array) $this->shipping));

                return true;
            }
        }
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

    public function request_pickup($data)
    {
        return false;
    }
}
