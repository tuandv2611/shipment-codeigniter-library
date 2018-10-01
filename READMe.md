# Shipment Library

Shipment is built on CodeIgniter 2.x.x drivers structure.
It is up to date every time when we have new ship connected as my customer

- ABF
- DYLT
- Echo
- Estes
- YRC

## Installation

CI.2.x.x

Put the Code in src to your libraries path of project

## Callation

Call shipment driver
```php
 $this->load->driver('shipment');
 $shipment = new Shipment;
```


Set full methods
```php
$shipment->set_adapter('abf');

if ($shipment->accept_method('aquotexml'))
{
    $shipment->set_delivery_type()
             ->set_credential()
             ->set_bill_term()
             ->set_shipper_info()
             ->set_consignee_info()
             ->set_pakage()
             ->set_other_options();

}
```

Get Shipment output

```php
$shipping = $shipment->request();
```


