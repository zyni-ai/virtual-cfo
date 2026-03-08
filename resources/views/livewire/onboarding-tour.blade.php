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
        async startTour() {
            if (window.driver?.js?.driver) {
                this.runTour();
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css';
            document.head.appendChild(link);

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js';
            script.onload = () => this.runTour();
            document.head.appendChild(script);
        },
        runTour() {
            const driverFn = window.driver.js.driver;
            const rawSteps = wire.$get('steps');

            const steps = rawSteps.map(step => ({
                element: step.element || undefined,
                popover: {
                    title: step.title,
                    description: step.description,
                    side: step.element ? 'bottom' : 'over',
                    align: step.element ? 'start' : 'center',
                },
            }));

            const tourDriver = driverFn({
                showProgress: true,
                animate: true,
                allowClose: true,
                overlayColor: 'rgba(0, 0, 0, 0.6)',
                steps: steps,
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
