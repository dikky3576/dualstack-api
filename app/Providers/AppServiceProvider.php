<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Email atur ulang password: link mengarah ke frontend, teks Indonesia.
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = rtrim((string) config('app.frontend_url'), '/')
                .'/reset-password?token='.urlencode($token)
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());

            $minutes = (int) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                60
            );

            return (new MailMessage)
                ->subject('Atur ulang password '.config('app.name'))
                ->greeting('Halo '.($notifiable->name ?? '').',')
                ->line('Kami menerima permintaan untuk mengatur ulang password akunmu.')
                ->action('Atur ulang password', $url)
                ->line("Link ini berlaku selama {$minutes} menit dan hanya dapat dipakai sekali.")
                ->line('Jika kamu tidak meminta ini, abaikan saja email ini. Passwordmu tidak akan berubah.')
                ->salutation('Salam, '.config('app.name'));
        });
    }
}
