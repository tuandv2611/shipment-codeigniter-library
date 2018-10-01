<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 *  @author :
 *  date    : 08 Apr, 2017
 *  Easy Shipment with Abf
 *  version: 1.0
 */

class Shipment_echo extends CI_Driver
{
    /**
     * Stores shipping object
     *
     * @var object
     */
    public $shipping            = null;

    // country
    public $country             = 'US';

    // country
    public $number_of_rate      = 5;

    // post_data
    protected $post_data        = [];

    // format default
    protected $format           = 'json';

    // version default
    protected $version          = '1.0';

    // allowed methods
    protected $allowed_methods  = ['ping' => 'GET', 'rates' => 'POST', 'shipments' => 'POST'];

    // format default
    protected $default_method   = 'ping';

    // method_name
    protected $method_name      = '';

    // service type
    public $service_type        = 'LTL';

    // errors
    protected $errors           = [];

    // errors
    protected $credential       = [];

    // url request
    //aquotexml.asp?
    private $url                = 'https://restapi.echo.com/v1/';

    // construct
    public function __construct($option = array())
    {
        $this->shipping = new stdClass();
    }

    public function request()
    {
        $url = $this->url;
        $body = $this->post_data;

        log_message('debug', print_r('Echo REQUEST', 1));
        log_message('debug', print_r(json_encode($body), 1));
        try {
            $client = new GuzzleHttp\Client([
                'base_uri' => $url,
                'timeout' => 10.0,
            ]);
            $method = $this->allowed_methods[$this->method_name];
            $response = $client->request($method, $this->method_name, [
                'body' => json_encode($body),
                'auth' => $this->credential,
                'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
                // 'verify' => false,
            ]);
            $this->shipping->response = $response->getBody()->getContents();

            log_message('debug', print_r('Echo RESPONSE', 1));
            log_message('debug', print_r(json_encode($this->shipping->response), 1));
            return self::output();

        } catch (Exception $e) {
            if ($e->hasResponse()) {
                $this->shipping->response = $e->getResponse()->getBody()->getContents();

                log_message('debug', print_r('Echo RESPONSE', 1));
                log_message('debug', print_r(json_encode($this->shipping->response), 1));

                return self::output();
            }
        }

    }

    public function output()
    {
        $response = json_decode($this->shipping->response);

        if (isset($response->ResponseStatus) and is_object($response->ResponseStatus)) {
            if (is_object($response->ResponseStatus) and is_array($response->ResponseStatus->Errors))
            {
                foreach($response->ResponseStatus->Errors as $error)
                {
                    self::set_errors((string) $error->Message);
                }
            }

            return false;
        }

        if (isset($response->Rates) and is_array($response->Rates))
        {
            $this->shipping->rates = [];
            foreach ($response->Rates as $rate)
            {
                if (count($this->shipping->rates) >= $this->number_of_rate)
                {
                    break;
                }
                array_push($this->shipping->rates, $rate);

            }

            $this->shipping->quote_number = 'NA';
            $CI = &get_instance();
            $CI->session->set_userdata('shipping_quote', json_encode((array) $this->shipping));

            return true;
        }

    }

    public function accept_method($name = '')
    {
        if ('' == $name)
        {
            $name = $this->default_method;
        }

        $this->method_name = $name;
        if (!in_array($this->method_name, array_keys($this->allowed_methods)))
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
            foreach ($types as $type)
            {
                $this->post_data['DestinationAccessorials'][] = $type->service_code;
            }
        }
        return $this;
    }

    // Set credentail to login api
    public function set_credential($credential)
    {
        if (is_array($credential)) $credential = (object) $credential;
        array_push($this->credential, $credential->username);
        array_push($this->credential, $credential->password);

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

        $this->post_data['OriginPostalCode'] = $shipper->zip;
        $this->post_data['OriginCountryCode'] = $this->country;
        $this->post_data['OriginAccessorials'] = [];

        return $this;
    }

    // Set consignee information
    public function set_consignee_info($consignee)
    {
        if (is_array($consignee)) $consignee = (object) $consignee;

        $this->post_data['DestinationPostalCode'] = $consignee->ship_zip;
        $this->post_data['DestinationCountryCode'] = $this->country;

        return $this;
    }

    public function set_pakage($items)
    {
        if (is_array($items) || is_object($items))
        {
            $this->post_data['PalletQuantity'] = 0;
            foreach ($items as $index => $item)
            {
                if (!is_object($item)) {
                    $item = (object) $item;
                }

                $this->post_data['Items'][] = [
                    'NmfcClass' => $item->ship_class,
                    'Weight' => $item->weight,
                ];
                $this->post_data['PalletQuantity'] += 1;
            }
        }

        return $this;
    }

    public function set_other_options($options = null)
    {
        if (!is_object($options)) $options = (object) $options;

        $this->post_data['UnitOfWeight']     = 'LB';

        !isset($options->ship_date) and $options->ship_date = date('m/d/Y');
        if (strtotime($options->ship_date))
        {
            $this->post_data['PickUpDate']   = date('m/d/Y', strtotime($options->ship_date));
        }

        return $this;
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
