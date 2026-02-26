<?php

namespace Database\Seeders;

use App\Models\AccountHead;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AccountHeadSeeder extends Seeder
{
    /**
     * Seed the default Indian chart of accounts (Tally-standard).
     *
     * Uses firstOrCreate() for idempotency — safe to run multiple times.
     */
    public function run(?Company $company = null): void
    {
        $companyId = $company?->id;

        foreach ($this->chartOfAccounts() as $primaryGroup) {
            $parent = AccountHead::firstOrCreate(
                [
                    'name' => $primaryGroup['name'],
                    'group_name' => $primaryGroup['name'],
                    'company_id' => $companyId,
                ],
                [
                    'parent_id' => null,
                    'is_active' => true,
                ]
            );

            foreach ($primaryGroup['children'] ?? [] as $child) {
                $subGroup = AccountHead::firstOrCreate(
                    [
                        'name' => $child['name'],
                        'group_name' => $primaryGroup['name'],
                        'company_id' => $companyId,
                    ],
                    [
                        'parent_id' => $parent->id,
                        'is_active' => true,
                    ]
                );

                foreach ($child['children'] ?? [] as $leafName) {
                    AccountHead::firstOrCreate(
                        [
                            'name' => $leafName,
                            'group_name' => $primaryGroup['name'],
                            'company_id' => $companyId,
                        ],
                        [
                            'parent_id' => $subGroup->id,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Standard Indian Tally chart of accounts structure.
     *
     * @return array<int, array{name: string, children?: array<int, array{name: string, children?: list<string>}>}>
     */
    private function chartOfAccounts(): array
    {
        return [
            [
                'name' => 'Capital Account',
            ],
            [
                'name' => 'Current Assets',
                'children' => [
                    [
                        'name' => 'Bank Accounts',
                        'children' => [
                            'HDFC Bank',
                            'ICICI Bank',
                            'SBI',
                            'Axis Bank',
                        ],
                    ],
                    [
                        'name' => 'Cash-in-Hand',
                        'children' => [
                            'Cash',
                        ],
                    ],
                    [
                        'name' => 'Deposits (Asset)',
                    ],
                    [
                        'name' => 'Loans & Advances (Asset)',
                    ],
                    [
                        'name' => 'Stock-in-Hand',
                    ],
                    [
                        'name' => 'Sundry Debtors',
                    ],
                ],
            ],
            [
                'name' => 'Current Liabilities',
                'children' => [
                    [
                        'name' => 'Duties & Taxes',
                        'children' => [
                            'GST Input CGST',
                            'GST Input SGST',
                            'GST Input IGST',
                            'GST Output CGST',
                            'GST Output SGST',
                            'GST Output IGST',
                            'TDS Receivable',
                            'TDS Payable',
                            'Professional Tax',
                        ],
                    ],
                    [
                        'name' => 'Provisions',
                    ],
                    [
                        'name' => 'Sundry Creditors',
                    ],
                ],
            ],
            [
                'name' => 'Direct Expenses',
                'children' => [
                    [
                        'name' => 'Purchases',
                    ],
                    [
                        'name' => 'Freight Inward',
                    ],
                ],
            ],
            [
                'name' => 'Direct Incomes',
                'children' => [
                    [
                        'name' => 'Sales',
                    ],
                    [
                        'name' => 'Service Revenue',
                    ],
                ],
            ],
            [
                'name' => 'Fixed Assets',
            ],
            [
                'name' => 'Indirect Expenses',
                'children' => [
                    [
                        'name' => 'Salary',
                    ],
                    [
                        'name' => 'Rent',
                    ],
                    [
                        'name' => 'Electricity',
                    ],
                    [
                        'name' => 'Internet',
                    ],
                    [
                        'name' => 'Telephone',
                    ],
                    [
                        'name' => 'Printing & Stationery',
                    ],
                    [
                        'name' => 'Travelling',
                    ],
                    [
                        'name' => 'Conveyance',
                    ],
                    [
                        'name' => 'Professional Fees',
                    ],
                    [
                        'name' => 'Audit Fees',
                    ],
                    [
                        'name' => 'Bank Charges',
                    ],
                    [
                        'name' => 'Depreciation',
                    ],
                    [
                        'name' => 'Insurance',
                    ],
                    [
                        'name' => 'Repairs & Maintenance',
                    ],
                    [
                        'name' => 'Miscellaneous Expenses',
                    ],
                ],
            ],
            [
                'name' => 'Indirect Incomes',
                'children' => [
                    [
                        'name' => 'Interest Received',
                    ],
                    [
                        'name' => 'Dividend Income',
                    ],
                    [
                        'name' => 'Rent Received',
                    ],
                    [
                        'name' => 'Miscellaneous Income',
                    ],
                ],
            ],
            [
                'name' => 'Investments',
            ],
            [
                'name' => 'Loans (Liability)',
            ],
            [
                'name' => 'Miscellaneous Expenses (Asset)',
            ],
            [
                'name' => 'Suspense A/c',
            ],
            [
                'name' => 'Branch / Divisions',
            ],
        ];
    }
}
