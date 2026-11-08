<?php

namespace App\Providers;

use App\Support\EmailVerificationLink;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
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

        // Email verifikasi: link ke frontend, teks Indonesia.
        VerifyEmail::createUrlUsing(
            fn (object $notifiable) => EmailVerificationLink::url($notifiable)
        );

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verifikasi email '.config('app.name'))
                ->greeting('Halo '.($notifiable->name ?? '').',')
                ->line('Terima kasih sudah mendaftar. Klik tombol di bawah untuk memverifikasi alamat emailmu.')
                ->action('Verifikasi email', $url)
                ->line('Link ini berlaku selama 24 jam.')
                ->line('Jika kamu tidak membuat akun, abaikan saja email ini.')
                ->salutation('Salam, '.config('app.name'));
        });
    }
}
