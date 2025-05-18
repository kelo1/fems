<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CertificateCreatedNotification extends Notification
{
    use Queueable;

    public $certificate;
    public $creator;

    /**
     * Create a new notification instance.
     *
     * @param  mixed  $certificate
     * @param  mixed  $creator
     */
    public function __construct($certificate, $creator)
    {
        $this->certificate = $certificate;
        $this->creator = $creator;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Certificate Created')
            ->greeting('Hello Admin,')
            ->line('A new certificate has been created by: ' . ($this->creator->name ?? ''))
            ->line('Certificate Serial Number: **' . $this->certificate->serial_number . '**')
            ->line('Thank you for using Guardian Safety App!');
    }

    /**
     * Get the array representation of the notification (for database).
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'certificate_id' => $this->certificate->id,
            'certificate_name' => $this->certificate->name ?? '',
            'serial_number' => $this->certificate->serial_number,
            'creator_id' => $this->creator->id,
            'title'            => 'New Certificate has been created',
            'message' => 'A new certificate has been created by a FSA Agent.',
            'status'           => 'unread',
        ];
    }

  
}
