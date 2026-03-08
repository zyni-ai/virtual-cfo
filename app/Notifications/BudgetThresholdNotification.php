<?php

namespace App\Notifications;

use App\Models\Budget;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BudgetThresholdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Budget $budget,
        public float $actual,
        public float $percentage,
        public int $threshold,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($this->threshold >= 100) {
            return ['database', 'mail'];
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $isExceeded = $this->threshold >= 100;

        return FilamentNotification::make()
            ->title($isExceeded ? 'Budget exceeded' : 'Budget warning')
            ->body(sprintf(
                '%s has reached %s%% of its budget (₹%s / ₹%s)',
                $this->headName(),
                number_format($this->percentage, 1),
                number_format($this->actual, 2),
                number_format((float) $this->budget->amount, 2),
            ))
            ->when($isExceeded, fn (FilamentNotification $n) => $n->danger(), fn (FilamentNotification $n) => $n->warning())
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $headName = $this->headName();

        return (new MailMessage)
            ->subject("Budget Exceeded: {$headName}")
            ->line(sprintf(
                'The account head "%s" has exceeded its budget.',
                $headName,
            ))
            ->line(sprintf(
                'Actual spend: ₹%s / Budget: ₹%s (%s%%)',
                number_format($this->actual, 2),
                number_format((float) $this->budget->amount, 2),
                number_format($this->percentage, 1),
            ))
            ->line('Please review your spending.');
    }

    private function headName(): string
    {
        return $this->budget->accountHead?->name ?? 'Unknown';
    }
}
