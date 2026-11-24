<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription, public int $days) {}

    public function via($notifiable): array { return ['mail', 'database']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject("Your plan expires in {$this->days} day(s)")
            ->line("Your {$this->subscription->plan->name} plan ends on {$this->subscription->ends_at->format('d M Y')}.")
            ->line('Renew to keep receiving bookings.')->action('Manage subscription', url('/photographer/subscription'));
    }

    public function toArray($notifiable): array
    {
        return ['subscription_id' => $this->subscription->id, 'days_left' => $this->days];
    }
}
