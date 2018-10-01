<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Shipment_estes extends CI_Driver
{

    public $shipping = null;

    protected $quote_url = 'https://www.estes-express.com/tools/rating/ratequote/v3.0/services/RateQuoteService?wsdl';

    protected $quote_ns = 'http://ws.estesexpress.com/ratequote';

    protected $pickup_url = "https://api.estes-express.com/ws/estesrtpickup.base.ws.provider.soapws:pickupRequestSSL/PickupPort/?wsdl";

    protected $location_url = "https://api.estes-express.com:443/ws/estesrtpickup.base.ws.provider.soapws:pickupRequestSSL/PickupPort";

    private $rateRequest = [];

    private $pickupRequest = [];

    // format default
    protected $allowed_methods = ['rates'];

    // format default
    protected $default_method = 'rates';

    // method_name
    protected $method_name = 'rates';

    // errors
    protected $errors = [];

    // soap client to get quotes
    protected $rate_client;

    // soap client to schedule pickup
    protected $pickpup_client;

    // construct
    public function __construct($option = array())
    {
        $this->rate_client = new SoapClient($this->quote_url, array("trace" => 1, "exception" => 0));
        $this->shipping = new stdClass();
    }

    public function request()
    {
        // log_message('debug','rateRequest');
        // log_message('debug', print_r($this->rateRequest,1));

        try {
            $this->shipping->response = $this->rate_client->getQuote($this->rateRequest);
        } catch (Exception $ex) {
            $res = $this->rate_client->__getLastRequest();
            log_message('debug','Estes soap expection');
            log_message('debug', print_r($ex,1));
            log_message('debug', print_r($res,1));
        }

        // log_message('debug', print_r('ESTES RESPONSE', 1));
        // log_message('debug', print_r($this->shipping->response, 1));

        return self::output();
    }

    public function output()
    {
        if (isset($this->shipping->response->quote->quoteNumber)) {
            $rate = new stdClass();

            if (is_array($this->shipping->response->quote->pricing->price)) {
                $rate->charge = $this->shipping->response->quote->pricing->price[0]->standardPrice;
                $rate->due_date = $this->shipping->response->quote->pricing->price[0]->deliveryDate;
            } else {
                $rate->charge = $this->shipping->response->quote->pricing->price->standardPrice;
                $rate->due_date = $this->shipping->response->quote->pricing->price->deliveryDate;
            }
            $rate->quote_number = $this->shipping->response->quote->quoteNumber;
            $rate->quote_date = $this->rateRequest['pickup']['date'];

            if (! empty($rate)) {
                $this->shipping->rate = $rate;

                $CI = &get_instance();
                $CI->session->set_userdata('shipping_quote', json_encode((array) $this->shipping));

                return true;
            }
        }

        return false;
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
        $accessorials = [];

        if (is_array($types) and count($types))
        {
            foreach ($types as $type)
            {
                $accessorials[] = $type->service_code;
            }

            $this->rateRequest['accessorials'] = $accessorials;
        }

        return $this;
    }

    // Set credentail to login api
    public function set_credential($credential)
    {
        if (is_array($credential)) $credential = (object) $credential;

        $headerBody = array(
            'user' => $credential->username,
            'password' => $credential->password
        );

        $this->rateRequest['account'] = $credential->account_num;

        $header = new SOAPHeader($this->quote_ns,'auth',$headerBody);
        $this->rate_client->__setSoapHeaders($header);

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

        $originPoint = [
            'countryCode' => $shipper->country,
            'postalCode' => $shipper->zip,
            'city' => $shipper->city,
            'stateProvince' => $shipper->state
        ];

        $this->rateRequest['originPoint'] = $originPoint;

        return $this;
    }

    // Set consignee information
    public function set_consignee_info($consignee)
    {
        if (is_array($consignee)) $consignee = (object) $consignee;

        $destinationPoint = [
            'countryCode' => $consignee->ship_country,
            'postalCode' => $consignee->ship_zip,
            'city' => $consignee->ship_city,
            'stateProvince' => $consignee->ship_state
        ];

        $this->rateRequest['destinationPoint'] = $destinationPoint;

        return $this;
    }

    public function set_pakage($items)
    {
        $baseCommodities = [];
        if (is_array($items) || is_object($items))
        {
            foreach ($items as $index => $item)
            {
                if (!is_object($item)) {
                    $item = (object) $item;
                }

                $weight_in_pounds = $item->weight;
                if ($item->weight_unit == 'oz') {
                    $weight_in_pounds = $weight_in_pounds / 16;
                }

                for ($x = 0; $x < $item->qty; $x++) {
                    $baseCommodity = array(
                        'class' => $item->ship_class,
                        'weight' => $weight_in_pounds
                    );

                    array_push($baseCommodities, $baseCommodity);
                }
            }
        }

        $this->rateRequest['baseCommodities'] = $baseCommodities;

        return $this;
    }

    public function set_other_options($options = null)
    {
        if (!is_object($options)) $options = (object) $options;

        $pickup = [];

        $date = new DateTime("now", new DateTimeZone('America/New_York') );

        $options->ship_date = $date->format('Y-m-d');

        if (strtotime($options->ship_date))
        {
            $pickup['date'] = $options->ship_date;
        }

        // C = Consignee
        $payor = "S";

        // P = Prepaid
        $terms = "P";
        $po = "";
        if (strlen($options->purchase_number) > 7) {
            $po = $options->customer_id.'-'.substr($options->purchase_number,0,7);
        } else {
            $po = $options->customer_id.'-'.$options->purchase_number;
        }
        $this->rateRequest['requestID'] = $po;


        $this->rateRequest['payor'] = $payor;
        $this->rateRequest['terms'] = $terms;
        $this->rateRequest['pickup'] = $pickup;
        return $this;
    }

    public function request_pickup($data)
    {
        // log_message('debug','request_pickup');
        // log_message('debug', print_r($data,1));

        $parameters = array();

        $po = "";
        if (strlen($data->order->order_info->order_no) > 7) {
            $po = $data->order->order_info->customer_id.'-'.substr($data->order->order_info->order_no,0,7);
        } else {
            $po = $data->order->order_info->customer_id.'-'.$data->order->order_info->order_no;
        }
        $parameters['pickupRequestInput']['requestNumber'] = $po;

        $shipper = array(
            'shipperName' => $data->shipper_info->company_name,
            'accountCode' => $data->api_info->account_num,
            'shipperAddress' => [
                'addressInfo' => [
                    'addressLine1' => $data->shipper_info->address,
                    'city' => $data->shipper_info->city,
                    'stateProvince' => $data->shipper_info->state,
                    'postalCode' => $data->shipper_info->zip,
                    'countryAbbrev' => $data->shipper_info->country
                ]
            ]
        );

        $parameters['pickupRequestInput']['shipper'] = $shipper;

        $parameters['pickupRequestInput']['requestAction'] = 'LL';
        $parameters['pickupRequestInput']['paymentTerms'] = 'PPD';

        $pickupDate = new DateTime('now');

        $parameters['pickupRequestInput']['pickupDate'] = date('Y-m-d', strtotime('now'));

        $start_pickup_time = $data->order->start_pickup_time;
        $end_pickup_time = $data->order->end_pickup_time;
        switch($data->shipper_info->timezone_id) {
            case 1:
            case 5:
                break;
            case 2:
            case 6:
                $start_pickup_time += 100;
                $end_pickup_time += 100;
                break;
            case 3:
            case 4:
                $start_pickup_time += 200;
                $end_pickup_time += 200;
                break;
            case 7:
            case 8:
                $start_pickup_time += 300;
                $end_pickup_time += 300;
                break;
            default:
                break;
        }

        $parameters['pickupRequestInput']['pickupStartTime'] = (int)$data->order->start_pickup_time;
        $parameters['pickupRequestInput']['pickupEndTime'] = (int)$data->order->end_pickup_time;

        $total_items = 0;
        $total_weight = 0;

        $commodities = array();

        foreach($data->items as $item) {

            for($x=0; $x < $item->product_quantity; $x++){
                $commodity = new stdClass();
                $commodity->id = $item->product_sku . '-' . ($x + 1);
                $commodityInfo = array(
                    'description' => $item->product_description,
                    'pieces' => $item->item_per_package,
                    'weight' => $item->weight
                );

                $commodity->commodityInfo = $commodityInfo;

                array_push($commodities, $commodity);
            }

            $total_items += ($item->product_quantity * $item->item_per_package);
            $total_weight += ($item->product_quantity * $item->weight);
        }

        $parameters['pickupRequestInput']['totalPieces'] = $total_items;
        $parameters['pickupRequestInput']['totalWeight'] = $total_weight;
        $parameters['pickupRequestInput']['totalHandlingUnits'] = $total_items;
        $parameters['pickupRequestInput']['whoRequested'] = 'S';

        //$parameters['pickupRequestInput']['commodities'] = $commodities;

        // log_message('debug','request schedule_pickup');
        // log_message('debug', print_r(json_encode($parameters),1));

        try {
            $options = array(
                'login' => $data->api_info->username,
                'password' => $data->api_info->password,
                'trace' => 1
            );

            $this->pickpup_client = new SoapClient($this->pickup_url, $options);
            $this->pickpup_client->__setLocation($this->location_url);

            $pickup_response = $this->pickpup_client->createPickupRequestWS($parameters);

            if ($pickup_response) {
                if (isset($pickup_response->requestNumber)) {
                    log_message('debug','pickup requested');
                    log_message('debug', print_r($pickup_response ,1));

                    $pickup_info = new stdClass();
                    isset($pickup_response->requestNumber) and $pickup_info->pickup_id = (string) $pickup_response->requestNumber;

                    $this->shipping->pickup_info = $pickup_info;
                    $CI = &get_instance();

                    $CI->session->set_userdata('pickup_info', json_encode((array) $this->shipping));

                    return $this->shipping->pickup_info;
                }
            }
        }catch (Exception $ex) {
            log_message('debug', print_r($ex,1));
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

?>
