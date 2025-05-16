<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CertificateQrNotification extends Notification
{
    use Queueable;

    public $certificate;
    public $qrCodeUrl;

    /**
     * Create a new notification instance.
     *
     * @param  mixed  $certificate
     * @param  string $qrCodeUrl
     * @return void
     */
    public function __construct($certificate, $qrCodeUrl)
    {
        $this->certificate = $certificate;
        $this->qrCodeUrl = $qrCodeUrl;
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
            ->subject('New Certificate QR Code')
            ->greeting('Hello ' . ($notifiable->name ?? '') . ',')
            ->line('A QR code has been generated for your certificate (Serial: **' . $this->certificate->serial_number . '**).' . ' Your certificate is now verified.')
            ->action('View QR Code', $this->qrCodeUrl)
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
            'serial_number' => $this->certificate->serial_number,
            'qr_code_url' => $this->qrCodeUrl,
            'message' => 'A new QR code has been generated for your certificate.',
        ];
    }

     public function toDatabase($notifiable)
    {
        return [
            'serial_number'    => $this->certificate->serial_number,
            'recipient_type'   => 'FSA_AGENT',
            'recipient_id'     => $notifiable->id,
            'title'            => 'New Certificate QR Code',
            'message'          => 'A new QR code has been generated for certificate: ' . $this->certificate->serial_number . '. Your certificate is now verified.',
            'status'           => 'unread',
            'qr_code_url'      => $this->qrCodeUrl,
        ];
    }
}
