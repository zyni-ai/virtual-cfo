<?php

namespace App\Services\TallyImport;

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

class TallyMasterImportService
{
    /**
     * Import Tally "All Masters" XML into a company's account heads and bank accounts.
     */
    public function import(string $xmlContent, Company $company): TallyImportResult
    {
        $result = new TallyImportResult;

        $normalized = $this->normalizeEncoding($xmlContent);
        $xml = $this->parseXml($normalized);

        if (! $xml) {
            $result->errors[] = 'Invalid XML content — could not parse the file.';

            return $result;
        }

        $requestData = $xml->BODY->IMPORTDATA->REQUESTDATA ?? null;

        if (! $requestData || $requestData->count() === 0) {
            return $result;
        }

        DB::transaction(function () use ($requestData, $company, $result) {
            $this->importGroups($requestData, $company, $result);
            $this->importLedgers($requestData, $company, $result);
        });

        return $result;
    }

    /**
     * Convert UTF-16LE (with BOM) content to UTF-8.
     */
    public function normalizeEncoding(string $content): string
    {
        if (str_starts_with($content, "\xFF\xFE")) {
            $content = substr($content, 2);
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
            $content = (string) preg_replace('/encoding="UTF-16"/', 'encoding="UTF-8"', $content, 1);
        }

        // Strip characters invalid in XML that Tally exports sometimes include:
        // 1. Raw control characters (0x00-0x08, 0x0B, 0x0C, 0x0E-0x1F)
        // 2. XML character references to those values (e.g. &#4;)
        $content = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
        $content = (string) preg_replace('/&#x?[0-8bBcCeEfF];/', '', $content);

        return $content;
    }

    /**
     * Parse XML string into SimpleXMLElement, returning null on failure.
     */
    public function parseXml(string $xml): ?SimpleXMLElement
    {
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $parsed = simplexml_load_string($xml);

            return $parsed !== false ? $parsed : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    /**
     * Import GROUP elements as account heads with hierarchy (two-pass).
     */
    protected function importGroups(SimpleXMLElement $requestData, Company $company, TallyImportResult $result): void
    {
        $groups = $this->extractElements($requestData, 'GROUP');

        if (empty($groups)) {
            return;
        }

        $rootGroups = [];
        $childGroups = [];

        foreach ($groups as $group) {
            $parent = (string) ($group->PARENT ?? '');

            if ($parent === '' || $parent === $this->getGroupName($group)) {
                $rootGroups[] = $group;
            } else {
                $childGroups[] = $group;
            }
        }

        foreach ($rootGroups as $group) {
            $this->upsertAccountHead($group, $company, null, $result, isGroup: true);
        }

        foreach ($childGroups as $group) {
            $parentName = (string) $group->PARENT;
            $parentHead = AccountHead::query()
                ->where('company_id', $company->id)
                ->where('name', $parentName)
                ->whereNull('deleted_at')
                ->first();

            if (! $parentHead) {
                $result->warnings[] = "Parent group '{$parentName}' not found for group '{$this->getGroupName($group)}' — imported without parent.";
            }

            $this->upsertAccountHead($group, $company, $parentHead, $result, isGroup: true);
        }
    }

    /**
     * Import LEDGER elements as account heads, detecting bank-type ledgers.
     */
    protected function importLedgers(SimpleXMLElement $requestData, Company $company, TallyImportResult $result): void
    {
        $ledgers = $this->extractElements($requestData, 'LEDGER');

        foreach ($ledgers as $ledger) {
            $parentName = (string) ($ledger->PARENT ?? '');
            $parentHead = null;

            if ($parentName !== '') {
                $parentHead = AccountHead::query()
                    ->where('company_id', $company->id)
                    ->where('name', $parentName)
                    ->whereNull('deleted_at')
                    ->first();
            }

            $head = $this->upsertAccountHead($ledger, $company, $parentHead, $result, isGroup: false);

            if ($parentName === 'Bank Accounts' && $head) {
                $this->importBankAccount($ledger, $company, $result);
            }
        }
    }

    /**
     * Create or update a bank account from a bank-type ledger.
     */
    protected function importBankAccount(SimpleXMLElement $ledger, Company $company, TallyImportResult $result): void
    {
        $name = $this->getGroupName($ledger);
        $bankDetails = $ledger->{'BANKDETAILS.LIST'} ?? null;

        $accountNumber = null;
        $ifscCode = null;
        $branch = null;

        if ($bankDetails) {
            $accountNumber = (string) ($bankDetails->BANKACCOUNTNUMBER ?? '') ?: null;
            $ifscCode = (string) ($bankDetails->IFSCODE ?? '') ?: null;
            $branch = (string) ($bankDetails->BRANCHNAME ?? '') ?: null;
        }

        if (! $ifscCode && ! $accountNumber) {
            $result->warnings[] = "Bank ledger '{$name}' has no IFSC code or account number.";
        }

        $existing = BankAccount::query()
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            $existing->update(array_filter([
                'ifsc_code' => $ifscCode,
                'branch' => $branch,
                'account_number' => $accountNumber,
            ], fn ($v) => $v !== null));
            $result->bankAccountsUpdated++;
        } else {
            BankAccount::create([
                'company_id' => $company->id,
                'name' => $name,
                'account_number' => $accountNumber,
                'ifsc_code' => $ifscCode,
                'branch' => $branch,
                'account_type' => 'current',
                'is_active' => true,
            ]);
            $result->bankAccountsCreated++;
        }
    }

    /**
     * Create or update an account head from a GROUP or LEDGER element.
     */
    protected function upsertAccountHead(
        SimpleXMLElement $element,
        Company $company,
        ?AccountHead $parent,
        TallyImportResult $result,
        bool $isGroup,
    ): ?AccountHead {
        $name = $this->getGroupName($element);
        $guid = (string) ($element->GUID ?? '') ?: null;
        $groupName = $isGroup ? $name : ((string) ($element->PARENT ?? '') ?: null);

        $existing = null;

        if ($guid) {
            $existing = AccountHead::query()
                ->where('company_id', $company->id)
                ->where('tally_guid', $guid)
                ->whereNull('deleted_at')
                ->first();
        }

        if (! $existing) {
            $existing = AccountHead::query()
                ->where('company_id', $company->id)
                ->where('name', $name)
                ->where(function ($q) use ($groupName) {
                    if ($groupName !== null) {
                        $q->where('group_name', $groupName);
                    } else {
                        $q->whereNull('group_name');
                    }
                })
                ->whereNull('deleted_at')
                ->first();
        }

        $attributes = [
            'company_id' => $company->id,
            'name' => $name,
            'tally_guid' => $guid,
            'group_name' => $groupName,
            'parent_id' => $parent?->id,
            'is_active' => true,
        ];

        if ($existing) {
            $existing->update($attributes);

            if ($isGroup) {
                $result->groupsUpdated++;
            } else {
                $result->ledgersUpdated++;
            }

            return $existing;
        }

        $head = AccountHead::create($attributes);

        if ($isGroup) {
            $result->groupsCreated++;
        } else {
            $result->ledgersCreated++;
        }

        return $head;
    }

    /**
     * Extract all elements of a given type from TALLYMESSAGE wrappers.
     *
     * @return array<int, SimpleXMLElement>
     */
    protected function extractElements(SimpleXMLElement $requestData, string $elementType): array
    {
        $elements = [];

        foreach ($requestData->TALLYMESSAGE as $message) {
            if (isset($message->{$elementType})) {
                $elements[] = $message->{$elementType};
            }
        }

        return $elements;
    }

    /**
     * Get the name from a GROUP or LEDGER element (NAME child or NAME attribute).
     */
    protected function getGroupName(SimpleXMLElement $element): string
    {
        return (string) ($element->NAME ?? $element['NAME'] ?? '');
    }
}
