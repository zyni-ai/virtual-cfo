# Tally XML Import Format

> **Date**: February 2026
> **Reference**: `DayBook zysk april25.xml` (April 2025 export from Zysk's Tally ERP)
> **Related Issue**: #15

## Overview

Tally uses a proprietary XML format for data import. The reference file is a DayBook export containing master data (ledgers, groups) and 383 vouchers (transactions). This document captures the format for our export implementation.

**Important**: The reference file is UTF-16LE encoded. Our export should support both UTF-8 (for readability) and UTF-16LE (Tally's native format).

## Envelope Structure

```xml
<ENVELOPE>
  <HEADER>
    <TALLYREQUEST>Import Data</TALLYREQUEST>
  </HEADER>
  <BODY>
    <IMPORTDATA>
      <REQUESTDESC>
        <REPORTNAME>All Masters</REPORTNAME>
        <STATICVARIABLES>
          <SVCURRENTCOMPANY>Zysk Technologies Private Limited - 2025 - 2026</SVCURRENTCOMPANY>
        </STATICVARIABLES>
      </REQUESTDESC>
      <REQUESTDATA>
        <!-- One TALLYMESSAGE per entity -->
        <TALLYMESSAGE xmlns:UDF="TallyUDF">
          <!-- LEDGER, GROUP, VOUCHERTYPE, or VOUCHER -->
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>
```

## Voucher Types in Reference

| Type | Count | Description |
|------|-------|-------------|
| Journal | 170 | Expense bookings with GST breakup (multi-leg) |
| Payment | 130 | Bank outflows (debit party, credit bank) |
| Receipt | 45 | Bank inflows (debit bank, credit party) |
| Sales | 36 | Customer invoices |
| Debit Note | 1 | Adjustment |
| Credit Note | 1 | Adjustment |

**For our use case**, we primarily generate Payment and Receipt vouchers (bank transactions), and Journal vouchers (when invoice data provides GST breakup).

## Payment Voucher (Simple — Bank Transaction Without Invoice)

This is a 2-leg voucher: debit the party/expense, credit the bank.

```xml
<TALLYMESSAGE xmlns:UDF="TallyUDF">
  <VOUCHER REMOTEID="<guid>" VCHKEY="<guid>" VCHTYPE="Payment" ACTION="Create"
           OBJVIEW="Accounting Voucher View">
    <DATE>20250401</DATE>
    <VCHSTATUSDATE>20250401</VCHSTATUSDATE>
    <GUID>b24723fa-b553-44c7-81db-a183cecc65d1-00002b8a</GUID>
    <NARRATION>ACH/TATACAPFINSERLTD/ICIC0000000017230174/00L200145-1</NARRATION>
    <ENTEREDBY>accounts@zysk.in</ENTEREDBY>
    <VOUCHERTYPENAME>Payment</VOUCHERTYPENAME>
    <CMPGSTIN>29AABCZ5012F1ZG</CMPGSTIN>
    <PARTYLEDGERNAME>TATA CAPITAL LIMITED</PARTYLEDGERNAME>
    <VOUCHERNUMBER>1</VOUCHERNUMBER>
    <CMPGSTREGISTRATIONTYPE>Regular</CMPGSTREGISTRATIONTYPE>
    <CMPGSTSTATE>Karnataka</CMPGSTSTATE>
    <EFFECTIVEDATE>20250401</EFFECTIVEDATE>
    <!-- ... boilerplate boolean flags (all "No" by default) ... -->

    <!-- Debit leg: Party/Expense -->
    <ALLLEDGERENTRIES.LIST>
      <LEDGERNAME>TATA CAPITAL LIMITED</LEDGERNAME>
      <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
      <AMOUNT>-88609.51</AMOUNT>
      <!-- ... boilerplate empty .LIST elements ... -->
    </ALLLEDGERENTRIES.LIST>

    <!-- Credit leg: Bank Account -->
    <ALLLEDGERENTRIES.LIST>
      <LEDGERNAME>Icici Bank</LEDGERNAME>
      <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
      <AMOUNT>88609.51</AMOUNT>
      <BANKALLOCATIONS.LIST>
        <DATE>20250401</DATE>
        <INSTRUMENTDATE>20250401</INSTRUMENTDATE>
        <BANKERSDATE>20250401</BANKERSDATE>
        <TRANSACTIONTYPE>Cheque</TRANSACTIONTYPE>
        <TRANSFERMODE>NEFT</TRANSFERMODE>
        <INSTRUMENTNUMBER>ICIC0000000017230174S60287133</INSTRUMENTNUMBER>
        <AMOUNT>88609.51</AMOUNT>
      </BANKALLOCATIONS.LIST>
      <!-- ... boilerplate empty .LIST elements ... -->
    </ALLLEDGERENTRIES.LIST>
  </VOUCHER>
</TALLYMESSAGE>
```

### Key observations:
- **Negative amount = debit** (`ISDEEMEDPOSITIVE: Yes`)
- **Positive amount = credit** (`ISDEEMEDPOSITIVE: No`)
- Bank leg includes `BANKALLOCATIONS.LIST` with instrument details
- `HASCASHFLOW` is `Yes` for Payment/Receipt vouchers

## Journal Voucher (Full — With Invoice/GST Breakup)

This is a multi-leg voucher used when we have invoice data. Example: Assetpro invoice with GST and TDS.

```xml
<TALLYMESSAGE xmlns:UDF="TallyUDF">
  <VOUCHER VCHTYPE="Journal" ACTION="Create" OBJVIEW="Accounting Voucher View">
    <DATE>20250401</DATE>
    <NARRATION>Invoice No: ASPL/2439 payment towards Office Assistant and
      Housekeeping charges for the month of March 2025</NARRATION>
    <GSTREGISTRATIONTYPE>Regular</GSTREGISTRATIONTYPE>
    <STATENAME>Karnataka</STATENAME>
    <PARTYGSTIN>29AAQCA1895C1ZD</PARTYGSTIN>
    <PLACEOFSUPPLY>Karnataka</PLACEOFSUPPLY>
    <VOUCHERTYPENAME>Journal</VOUCHERTYPENAME>
    <PARTYLEDGERNAME>Assetpro Solution Pvt Ltd</PARTYLEDGERNAME>
    <CMPGSTIN>29AABCZ5012F1ZG</CMPGSTIN>
    <VOUCHERNUMBER>9</VOUCHERNUMBER>

    <!-- Leg 1: Expense (Debit) -->
    <ALLLEDGERENTRIES.LIST>
      <LEDGERNAME>Manpower Supply Charges</LEDGERNAME>
      <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
      <AMOUNT>-27500.00</AMOUNT>
      <!-- GST rate details for this ledger -->
      <RATEDETAILS.LIST>
        <GSTRATEDUTYHEAD>CGST</GSTRATEDUTYHEAD>
        <GSTRATEVALUATIONTYPE>Based on Value</GSTRATEVALUATIONTYPE>
      </RATEDETAILS.LIST>
      <RATEDETAILS.LIST>
        <GSTRATEDUTYHEAD>SGST/UTGST</GSTRATEDUTYHEAD>
        <GSTRATEVALUATIONTYPE>Based on Value</GSTRATEVALUATIONTYPE>
      </RATEDETAILS.LIST>
    </ALLLEDGERENTRIES.LIST>

    <!-- Leg 2: CGST (Debit) -->
    <ALLLEDGERENTRIES.LIST>
      <LEDGERNAME>Input Cgst @ 9%</LEDGERNAME>
      <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
      <AMOUNT>-2475.00</AMOUNT>
    </ALLLEDGERENTRIES.LIST>

    <!-- Leg 3: SGST (Debit) -->
    <ALLLEDGERENTRIES.LIST>
      <LEDGERNAME>Input Sgst @ 9%</LEDGERNAME>
      <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
      <AMOUNT>-2475.00</AMOUNT>
    </ALLLEDGERENTRIES.LIST>

    <!-- Leg 4: TDS (Credit) -->
    <ALLLEDGERENTRIES.LIST>
      <LEDGERNAME>TDS Assetpro</LEDGERNAME>
      <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
      <AMOUNT>550.00</AMOUNT>
    </ALLLEDGERENTRIES.LIST>

    <!-- Leg 5: Party/Vendor (Credit) -->
    <ALLLEDGERENTRIES.LIST>
      <LEDGERNAME>Assetpro Solution Pvt Ltd</LEDGERNAME>
      <ISPARTYLEDGER>Yes</ISPARTYLEDGER>
      <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
      <AMOUNT>31900.00</AMOUNT>
    </ALLLEDGERENTRIES.LIST>
  </VOUCHER>
</TALLYMESSAGE>
```

### Balance check:
| Leg | Amount | Side |
|-----|--------|------|
| Manpower Supply Charges | -27,500.00 | Debit |
| Input CGST @ 9% | -2,475.00 | Debit |
| Input SGST @ 9% | -2,475.00 | Debit |
| TDS Assetpro | +550.00 | Credit |
| Assetpro Solution Pvt Ltd | +31,900.00 | Credit |
| **Total** | **0.00** | **Balanced** |

## Boilerplate Fields

Tally expects many boolean flags and empty `.LIST` elements. Most are `No` by default. Key ones to include per voucher:

```xml
<!-- Boolean flags (all "No" unless stated) -->
<ISDELETED>No</ISDELETED>
<ISCANCELLED>No</ISCANCELLED>
<ISONHOLD>No</ISONHOLD>
<ISOPTIONAL>No</ISOPTIONAL>
<AUDITED>No</AUDITED>

<!-- Empty list elements Tally expects -->
<EWAYBILLDETAILS.LIST>      </EWAYBILLDETAILS.LIST>
<EXCLUDEDTAXATIONS.LIST>      </EXCLUDEDTAXATIONS.LIST>
<OLDAUDITENTRIES.LIST>      </OLDAUDITENTRIES.LIST>
<ACCOUNTAUDITENTRIES.LIST>      </ACCOUNTAUDITENTRIES.LIST>
<AUDITENTRIES.LIST>      </AUDITENTRIES.LIST>
```

**Note**: Empty `.LIST` elements in Tally XML use whitespace-only content (spaces), not self-closing tags. Our export should replicate this pattern.

## Ledger Name Matching

**Critical**: Ledger names in the XML must **exactly match** ledger names in the target Tally company. If we export `LEDGERNAME>ICICI Bank</LEDGERNAME>` but Tally has it as `Icici Bank`, the import will fail or create a duplicate ledger.

Options:
1. Pre-populate our `account_heads.name` from Tally's ledger list
2. Add a `tally_name` field to `account_heads` for the exact Tally mapping
3. Import Tally's master data (the `<LEDGER>` elements from the reference file)

The `tally_guid` column already exists on `account_heads` (currently empty) — it was built for this purpose.

## Receipt Voucher

Same as Payment but reversed — debit the bank, credit the party:

```xml
<VOUCHER VCHTYPE="Receipt" ACTION="Create">
  <VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME>
  <!-- Debit: Bank -->
  <ALLLEDGERENTRIES.LIST>
    <LEDGERNAME>Icici Bank</LEDGERNAME>
    <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
    <AMOUNT>-150000.00</AMOUNT>
  </ALLLEDGERENTRIES.LIST>
  <!-- Credit: Party -->
  <ALLLEDGERENTRIES.LIST>
    <LEDGERNAME>METAFIRST TECHNOLOGIES PRIVATE LIMITED</LEDGERNAME>
    <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
    <AMOUNT>150000.00</AMOUNT>
  </ALLLEDGERENTRIES.LIST>
</VOUCHER>
```

## Date Format

All dates in Tally XML use `YYYYMMDD` without separators:
- `20250401` = April 1, 2025
- Laravel: `$transaction->date->format('Ymd')`

## Amount Format

- Decimal with 2 places: `88609.51`, `27500.00`
- No currency symbols or comma separators
- Leading space in some fields (e.g., `<ALTERID> 16217</ALTERID>`) — appears to be Tally's convention for numeric fields

## Company Footer

The reference file ends with a company identity block:

```xml
<TALLYMESSAGE xmlns:UDF="TallyUDF">
  <COMPANY>
    <REMOTECMPINFO.LIST MERGE="Yes">
      <NAME>b24723fa-b553-44c7-81db-a183cecc65d1</NAME>
      <REMOTECMPNAME>Zysk Technologies Private Limited - 2025 - 2026</REMOTECMPNAME>
      <REMOTECMPSTATE>Karnataka</REMOTECMPSTATE>
    </REMOTECMPINFO.LIST>
  </COMPANY>
</TALLYMESSAGE>
```

This should be the last `TALLYMESSAGE` in the export.
