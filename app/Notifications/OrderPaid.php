<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email bukti pembayaran, dikirim sekali saat pesanan berubah menjadi lunas.
 */
class OrderPaid extends Notification
{
    public function __construct(public Order $order)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items');

        $mail = (new MailMessage)
            ->subject('Pembayaran berhasil — '.$order->order_number)
            ->greeting('Halo '.($notifiable->name ?? '').',')
            ->line("Pembayaran untuk pesanan **{$order->order_number}** sudah kami terima. Terima kasih!");

        foreach ($order->items as $item) {
            $mail->line('• '.$item->product_name.' — '.$this->rupiah($item->line_total));
        }

        if ((float) $order->coupon_discount > 0) {
            $mail->line('Kupon '.$order->coupon_code.': −'.$this->rupiah($order->coupon_discount));
        }

        return $mail
            ->line('**Total dibayar: '.$this->rupiah($order->total_amount).'**')
            ->action('Unduh template', rtrim((string) config('app.frontend_url'), '/').'/account/downloads')
            ->line('Simpan email ini sebagai bukti pembayaran.')
            ->salutation('Salam, '.config('app.name'));
    }

    private function rupiah(mixed $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
