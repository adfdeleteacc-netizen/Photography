<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking, public string $title, public string $line) {}

    public function via($notifiable): array { return ['mail', 'database']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->greeting("Hi {$notifiable->name},")
            ->line($this->line)->line("Booking reference: {$this->booking->reference}")
            ->action('View booking', url("/bookings/{$this->booking->id}"));
    }

    public function toArray($notifiable): array
    {
        return ['booking_id' => $this->booking->id, 'title' => $this->title, 'line' => $this->line];
    }
}
