<?php

namespace Modules\Package\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Business\Models\Business;

class TrialEndingSoon extends Notification
{
    public function __construct(private readonly Business $business) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $endsAt = $this->business->trial_ends_at;

        return (new MailMessage)
            ->subject('Your Zeebroo free trial ends soon')
            ->greeting('Hi '.($notifiable->name ?? 'there').',')
            ->line('The free trial for **'.$this->business->name.'** ends on '.$endsAt?->format('d M Y, g:i A').'.')
            ->line('Choose a plan before then to keep using Zeebroo without interruption.')
            ->action('Choose a plan', route('plan.choose'))
            ->line('Features that aren\'t in the plan you choose will be hidden. Your data stays safe and comes back if you upgrade later.');
    }
}
