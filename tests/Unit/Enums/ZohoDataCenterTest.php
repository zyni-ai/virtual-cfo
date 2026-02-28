<?php

use App\Enums\ZohoDataCenter;

describe('ZohoDataCenter enum', function () {
    it('returns correct accounts URL for each data center', function (ZohoDataCenter $dataCenter, string $expectedUrl) {
        expect($dataCenter->accountsUrl())->toBe($expectedUrl);
    })->with([
        'India' => [ZohoDataCenter::India, 'https://accounts.zoho.in'],
        'US' => [ZohoDataCenter::Us, 'https://accounts.zoho.com'],
        'EU' => [ZohoDataCenter::Eu, 'https://accounts.zoho.eu'],
        'Australia' => [ZohoDataCenter::Australia, 'https://accounts.zoho.com.au'],
        'Japan' => [ZohoDataCenter::Japan, 'https://accounts.zoho.jp'],
    ]);

    it('returns correct API URL for each data center', function (ZohoDataCenter $dataCenter, string $expectedUrl) {
        expect($dataCenter->apiUrl())->toBe($expectedUrl);
    })->with([
        'India' => [ZohoDataCenter::India, 'https://www.zohoapis.in'],
        'US' => [ZohoDataCenter::Us, 'https://www.zohoapis.com'],
        'EU' => [ZohoDataCenter::Eu, 'https://www.zohoapis.eu'],
        'Australia' => [ZohoDataCenter::Australia, 'https://www.zohoapis.com.au'],
        'Japan' => [ZohoDataCenter::Japan, 'https://www.zohoapis.jp'],
    ]);

    it('returns human-readable labels', function (ZohoDataCenter $dataCenter) {
        expect($dataCenter->getLabel())->toBeString()->not->toBeEmpty();
    })->with(ZohoDataCenter::cases());

    it('can be created from string value', function () {
        expect(ZohoDataCenter::from('in'))->toBe(ZohoDataCenter::India)
            ->and(ZohoDataCenter::from('us'))->toBe(ZohoDataCenter::Us);
    });
});
