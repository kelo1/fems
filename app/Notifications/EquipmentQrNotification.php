<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EquipmentQrNotification extends Notification
{
    use Queueable;

    public $equipment;
    public $qrCodeUrl;

    /**
     * Create a new notification instance.
     *
     * @param  mixed  $equipment
     * @param  string $qrCodeUrl
     * @return void
     */
    public function __construct($equipment, $qrCodeUrl)
    {
        $this->equipment = $equipment;
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
            ->subject('New Equipment QR Code')
            ->greeting('Hello ' . ($notifiable->name ?? '') . ',')
            ->line('A QR code has been generated for your equipment (Serial: **' . $this->equipment->serial_number . '**).' . ' Your equipment is now active.')
            ->action('View QR Code', $this->qrCodeUrl)
            ->line('Thank you for using Guardian Safety App!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'serial_number' => $this->equipment->serial_number,
            'qr_code_url' => $this->qrCodeUrl,
        ];
    }

     public function toDatabase($notifiable)
    {
        return [
            'serial_number'    => $this->equipment->serial_number,
            'recipient_type'   => 'SERVICE_PROVIDER',
            'recipient_id'     => $notifiable->id,
            'title'            => 'New Equipment QR Code',
            'message'          => 'A new QR code has been generated for equipment: ' . $this->equipment->serial_number . '. Your equipment is now active.',
            'status'           => 'unread',
            'qr_code_url'      => $this->qrCodeUrl,
        ];
    }
}
