<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ForgotPasswordNotification extends Notification
{
    use Queueable;

    protected $user;
    protected $user_name;
    protected $toAddress;
    protected $email_id;
    protected $user_type;

    /**
     * Create a new notification instance.
     *
     * @param  mixed $user
     * @param  string $user_name
     * @param  string $toAddress
     * @param  string $email_id
     * @param  string $user_type
     */
    public function __construct($user, $user_name, $toAddress, $email_id, $user_type)
    {
        $this->user = $user;
        $this->user_name = $user_name;
        $this->toAddress = $toAddress;
        $this->email_id = $email_id;
        $this->user_type = $user_type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        // Generate the reset password URL
        $url = URL::to('https://guardiansafetyapp.com/reset_password/?email_token=');

        // Append the user type and ID to the URL
        $resetPasswordUrl = $url . $this->email_id . '&id=' . $this->user->id . '&user_type=' . $this->user_type;

        return (new MailMessage)
            ->greeting('Hello ' . $this->user_name . ',')
            ->from('admin@guardiansafetyapp.com')
            ->replyTo($this->toAddress)
            ->subject('Reset Your Password')
            ->line('We received a request to reset your password. Click the link below to reset it.')
            ->action('Reset Password', $resetPasswordUrl)
            ->line('If you did not request a password reset, please ignore this email.')
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'user_id' => $this->user->id,
            'user_type' => $this->user_type,
            'email_id' => $this->email_id,
        ];
    }
}
