<?php

return [
    'dashboard' => [
        [
            'title' => 'Your Dashboard',
            'description' => 'This is your home base. It shows key stats and recent activity across all your financial data.',
            'element' => null,
        ],
        [
            'title' => 'Quick Stats',
            'description' => 'These cards show your totals at a glance — imports processed, transactions parsed, and mapping progress.',
            'element' => '.fi-wi-stats-overview',
        ],
        [
            'title' => 'Navigation',
            'description' => 'Use the sidebar to move between sections. The workflow flows: Import → Transactions → Map → Export.',
            'element' => '.fi-sidebar-nav',
        ],
    ],

    'imported-files' => [
        [
            'title' => 'Import Statements',
            'description' => 'This is where you upload bank statements, credit card statements, or invoices. The system parses them automatically using AI.',
            'element' => null,
        ],
        [
            'title' => 'Upload Button',
            'description' => 'Click here to upload a new statement (PDF, CSV, or XLSX). Select the statement type and account before uploading.',
            'element' => '.fi-header-actions',
        ],
        [
            'title' => 'Processing Status',
            'description' => 'Track the status of each import — pending, processing, completed, or failed. Click any row to view details.',
            'element' => '.fi-ta',
        ],
    ],

    'transactions' => [
        [
            'title' => 'Your Transactions',
            'description' => 'After importing a statement, parsed transactions appear here. This is where you map them to account heads and export to Tally.',
            'element' => null,
        ],
        [
            'title' => 'Transaction Stats',
            'description' => 'A quick summary of your transactions — total count, mapped vs unmapped, and amounts.',
            'element' => '.fi-wi-stats-overview',
        ],
        [
            'title' => 'Table & Filters',
            'description' => 'Filter transactions by date, bank, status, or mapping type. Use bulk actions to map or export multiple transactions at once.',
            'element' => '.fi-ta',
        ],
    ],

    'account-heads' => [
        [
            'title' => 'Account Heads',
            'description' => 'Your Tally chart of accounts. These are the categories transactions get mapped to before exporting.',
            'element' => null,
        ],
        [
            'title' => 'Import from Tally',
            'description' => 'Use this button to import your chart of accounts from a Tally XML master file. This populates all heads automatically.',
            'element' => '.fi-header-actions',
        ],
        [
            'title' => 'Head Hierarchy',
            'description' => 'Account heads can be nested under parents to match your Tally structure. The table shows the full hierarchy.',
            'element' => '.fi-ta',
        ],
    ],

    'head-mappings' => [
        [
            'title' => 'Mapping Rules',
            'description' => 'Rules automatically map transactions to account heads based on their description. Once set, rules apply to all future imports.',
            'element' => null,
        ],
        [
            'title' => 'Create a Rule',
            'description' => 'Click here to create a new mapping rule. You can also create rules directly from the Transactions page.',
            'element' => '.fi-header-actions',
        ],
        [
            'title' => 'Existing Rules',
            'description' => 'Each rule has a pattern, match type (contains/exact/regex), and target account head. Rules are applied in priority order.',
            'element' => '.fi-ta',
        ],
    ],

    'reconciliation' => [
        [
            'title' => 'Reconciliation',
            'description' => 'Match bank transactions against invoices to enrich your Tally exports with GST breakdowns and vendor details.',
            'element' => null,
        ],
        [
            'title' => 'Reconciliation Stats',
            'description' => 'See how many transactions are matched, unmatched, or pending review.',
            'element' => '.fi-wi-stats-overview',
        ],
        [
            'title' => 'Match Transactions',
            'description' => 'Review and match transactions here. Use the actions to approve matches or manually link entries.',
            'element' => '.fi-ta',
        ],
    ],
];
