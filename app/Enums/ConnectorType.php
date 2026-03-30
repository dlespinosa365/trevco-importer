<?php

namespace App\Enums;

enum ConnectorType: string
{
    case NetSuite = 'netsuite';
    case AmazonVendorCentral = 'amazon_vendor_central';
    case DummyJson = 'dummy_json';

    public function label(): string
    {
        return match ($this) {
            self::NetSuite => 'NetSuite',
            self::AmazonVendorCentral => 'Amazon Vendor Central',
            self::DummyJson => 'Dummy JSON',
        };
    }
}
