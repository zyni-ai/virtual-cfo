<div>
    @if($showTour)
        <div
            x-data="onboardingTour($wire)"
            x-init="$nextTick(() => startTour())"
        ></div>
    @endif
</div>

@script
<script>
    Alpine.data('onboardingTour', (wire) => ({
        tourLoaded: false,
        async startTour() {
            if (window.driver?.js?.driver) {
                this.tourLoaded = true;
                this.runTour();
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css';
            document.head.appendChild(link);

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js';
            script.onload = () => {
                this.tourLoaded = true;
                this.runTour();
            };
            document.head.appendChild(script);
        },
        runTour() {
            const driverFn = window.driver.js.driver;

            const tourDriver = driverFn({
                showProgress: true,
                animate: true,
                allowClose: true,
                overlayColor: 'rgba(0, 0, 0, 0.6)',
                steps: [
                    {
                        popover: {
                            title: 'Welcome to Virtual CFO!',
                            description: "Let's take a quick tour of the main features. This takes about 30 seconds.",
                            side: 'over',
                            align: 'center',
                        },
                    },
                    {
                        element: '[data-sidebar-group]',
                        popover: {
                            title: 'Navigation',
                            description: 'Use the sidebar to navigate between sections: Imported Files, Transactions, Reconciliation, and Automation Rules.',
                            side: 'right',
                            align: 'start',
                        },
                    },
                    {
                        element: '[href*="imported-files"]',
                        popover: {
                            title: 'Step 1: Import Statements',
                            description: 'Start by uploading bank statements, credit card statements, or invoices (PDF, CSV, XLSX). The system will parse them automatically using AI.',
                            side: 'right',
                            align: 'start',
                        },
                    },
                    {
                        element: '[href*="transactions"]',
                        popover: {
                            title: 'Step 2: Review Transactions',
                            description: 'After parsing, review your transactions here. You can assign account heads manually, run AI matching, or export to Tally XML.',
                            side: 'right',
                            align: 'start',
                        },
                    },
                    {
                        element: '[href*="account-heads"]',
                        popover: {
                            title: 'Step 3: Account Heads',
                            description: 'Import your chart of accounts from a Tally XML file. These are used to categorize transactions for export.',
                            side: 'right',
                            align: 'start',
                        },
                    },
                    {
                        element: '[href*="head-mappings"]',
                        popover: {
                            title: 'Step 4: Mapping Rules',
                            description: 'Set up rules to automatically match transaction descriptions to account heads. Rules are applied to all future imports.',
                            side: 'right',
                            align: 'start',
                        },
                    },
                    {
                        element: '[href*="reconciliation"]',
                        popover: {
                            title: 'Step 5: Reconciliation',
                            description: 'Match bank transactions against invoices to enrich your Tally exports with GST breakdowns and vendor details.',
                            side: 'right',
                            align: 'start',
                        },
                    },
                    {
                        popover: {
                            title: "You're all set!",
                            description: 'The workflow is: Upload → Parse → Map → Export. You can restart this tour anytime from the user menu.',
                            side: 'over',
                            align: 'center',
                        },
                    },
                ],
                onDestroyStarted: () => {
                    tourDriver.destroy();
                    wire.completeTour();
                },
            });

            tourDriver.drive();
        },
    }));
</script>
@endscript
