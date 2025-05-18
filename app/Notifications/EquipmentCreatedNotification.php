<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class EquipmentCreatedNotification extends Notification
{
    use Queueable;

    public $equipment;
    public $serviceProvider;

    /**
     * Create a new notification instance.
     *
     * @param  mixed  $equipment
     * @param  mixed  $serviceProvider
     */
    public function __construct($equipment, $serviceProvider)
    {
        $this->equipment = $equipment;
        $this->serviceProvider = $serviceProvider;
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
            ->subject('New Equipment Created')
            ->greeting('Hello Admin,')
            ->line('A new equipment has been created by Service Provider: ' . ($this->serviceProvider->name ?? ''))
            ->line('Equipment Name: **' . $this->equipment->name . '**')
            ->line('Serial Number: **' . $this->equipment->serial_number . '**')
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
            'equipment_id' => $this->equipment->id,
            'equipment_name' => $this->equipment->name,
            'serial_number' => $this->equipment->serial_number,
            'service_provider_id' => $this->serviceProvider->id,
            'service_provider_name' => $this->serviceProvider->name ?? '',
            'title'            => 'New Equipment has been created',
            'message' => 'A new equipment has been created by a Service Provider.',
            'status'           => 'unread',
        ];
    }

  
}
