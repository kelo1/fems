<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use App\Client;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Client $client, $client_name, $toAddress, $email_id)
    {
        $this->client = $client;
        $this->client_name =$client_name;
        $this->toAddress = $toAddress;
        $this->email_id =$email_id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
       // $url=URL::to('/verify_email/?verifycode=');
       //forceRootUrl

      $url = URL::to('https://fems.com/verify_email/?verifycode=');

       //$url=URL::to('localhost:3000/test');
        return (new MailMessage)
        ->greeting('Hello '.$this->client_name.',')
        ->from('no-reply@i-visas.com')
        ->replyTo($this->toAddress)
        ->subject('Email Verification')
        ->line('Kindly click on the link below to verify your email.')
        ->action('Notification Action', url($url.$this->email_id.'&id='.$this->client->id))
     //   ->action('Notification Action', url($url))
        ->line('Thank you for using our application!');
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
            //
        ];
    }
}
