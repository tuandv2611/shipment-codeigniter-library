<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CodeIgniter Shipment Class
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @category    Core
 * @author
 * @link
 */

class Shipment extends CI_Driver_Library
{

    // sub driver for shipment
    protected $valid_drivers    = [
        'shipment_abf',
        'shipment_dylt',
        'shipment_echo_web',
        'shipment_yrc',
        'shipment_kuebix',
        'shipment_estes'
    ];

    // adapter
    protected $_adapter         = '';


    function __construct($options = [])
    {
        if (count($options))
        {
            $this->_adapter = current($options);
            if (isset($options['adapter']))
            {
                $this->_adapter = $options['adapter'];
            }
        }
    }

    // set adapter force
    public function set_adapter($adapter = '')
    {
        if ($adapter)
        {
            $this->_adapter = $adapter;
        }
    }

    public function accept_method($name = '')
    {
        if ( ! $this->is_supported($this->_adapter))
        {
            return false;
        }
        return $this->{$this->_adapter}->accept_method($name);
    }

    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */

    public function set_delivery_type($type = '')
    {
        return $this->{$this->_adapter}->set_delivery_type($type);
    }

    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */
    public function set_credential($credential)
    {
        return $this->{$this->_adapter}->set_credential($credential);
    }


    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */
    public function set_bill_term($bill_term = '')
    {
        return $this->{$this->_adapter}->set_bill_term($bill_term);
    }

    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */
    public function set_shipper_info($shipper)
    {
        return $this->{$this->_adapter}->set_shipper_info($shipper);
    }


    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */
    public function set_consignee_info($consignee)
    {
        return $this->{$this->_adapter}->set_consignee_info($consignee);
    }

    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */
    public function set_pakage($items)
    {
        return $this->{$this->_adapter}->set_pakage($items);
    }

    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */
    public function set_other_options($options = array())
    {
        return $this->{$this->_adapter}->set_other_options($options);
    }

    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */
    public function is_supported($driver)
    {
        static $support = array();
        if ( ! isset($support[$driver]))
        {
            $support[$driver] = $this->{$driver}->is_supported();
        }
        return $support[$driver];
    }

    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */
    public function get_message()
    {
        return $this->{$this->_adapter}->get_message();
    }

    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param   string  The driver to test.
     * @return  array
     */
    public function request()
    {

        if ($this->{$this->_adapter}->request())
        {
            return $this->{$this->_adapter}->shipping;
        }

        return false;
    }

    /**
     * Schedule pick-ups
     *
     * @param   string  The driver to test.
     * @return  array
     */

    public function request_pickup($data)
    {
        return $this->{$this->_adapter}->request_pickup($data);
    }

}
