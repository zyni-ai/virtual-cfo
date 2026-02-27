<?php

use App\Services\TallyImport\TallyMasterImportService;

$fixturesPath = dirname(__DIR__, 2).'/fixtures';

describe('TallyMasterImportService — Encoding', function () use ($fixturesPath) {
    it('converts UTF-16LE with BOM to UTF-8', function () use ($fixturesPath) {
        $service = new TallyMasterImportService;
        $utf16Content = file_get_contents($fixturesPath.'/tally-masters-utf16le.xml');

        $result = $service->normalizeEncoding($utf16Content);

        expect($result)->toContain('<?xml')
            ->and($result)->toContain('encoding="UTF-8"')
            ->and($result)->toContain('Zysk Technologies Private Limited')
            ->and($result)->not->toContain("\xFF\xFE");
    });

    it('passes through UTF-8 content unchanged', function () use ($fixturesPath) {
        $service = new TallyMasterImportService;
        $utf8Content = file_get_contents($fixturesPath.'/tally-masters-simple.xml');

        $result = $service->normalizeEncoding($utf8Content);

        expect($result)->toBe($utf8Content);
    });
});

describe('TallyMasterImportService — XML Parsing', function () use ($fixturesPath) {
    it('parses valid XML into SimpleXMLElement', function () use ($fixturesPath) {
        $service = new TallyMasterImportService;
        $xml = file_get_contents($fixturesPath.'/tally-masters-simple.xml');

        $result = $service->parseXml($xml);

        expect($result)->toBeInstanceOf(SimpleXMLElement::class)
            ->and((string) $result->BODY->IMPORTDATA->REQUESTDESC->REPORTNAME)->toBe('All Masters');
    });

    it('returns null for invalid XML', function () {
        $service = new TallyMasterImportService;

        $result = $service->parseXml('this is not xml');

        expect($result)->toBeNull();
    });

    it('parses UTF-16LE content after encoding conversion', function () use ($fixturesPath) {
        $service = new TallyMasterImportService;
        $utf16Content = file_get_contents($fixturesPath.'/tally-masters-utf16le.xml');

        $normalized = $service->normalizeEncoding($utf16Content);
        $result = $service->parseXml($normalized);

        expect($result)->toBeInstanceOf(SimpleXMLElement::class)
            ->and((string) $result->BODY->IMPORTDATA->REQUESTDESC->STATICVARIABLES->SVCURRENTCOMPANY)
            ->toBe('Zysk Technologies Private Limited');
    });
});
