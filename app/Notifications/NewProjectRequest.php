<?php

namespace App\Notifications;

use App\Models\ProjectRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email ke pemilik saat ada permintaan proyek baru.
 */
class NewProjectRequest extends Notification
{
    private const TYPE_LABELS = [
        'website' => 'Website',
        'web_app' => 'Aplikasi web',
        'template_custom' => 'Kustomisasi template',
        'other' => 'Lainnya',
    ];

    public function __construct(public ProjectRequest $request)
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
        $r = $this->request;

        $mail = (new MailMessage)
            ->subject('Permintaan proyek baru — '.$this->plain($r->name, 60))
            ->replyTo($r->email, $r->name)
            ->line('Ada permintaan proyek baru dari formulir di halaman Jasa.')
            ->line('**Nama:** '.$this->plain($r->name))
            ->line('**Email:** '.$this->plain($r->email))
            ->line('**WhatsApp:** '.($r->whatsapp ? $this->plain($r->whatsapp) : '-'))
            ->line('**Jenis:** '.(self::TYPE_LABELS[$r->project_type] ?? $r->project_type))
            ->line('**Target rilis:** '.($r->deadline ? $this->plain($r->deadline) : '-'))
            ->line('**Anggaran:** '.($r->budget ? $this->plain($r->budget) : '-'))
            ->line('**Gambaran:**')
            ->line($this->plain($r->description, 3000));

        return $mail->action(
            'Buka di dasbor admin',
            rtrim((string) config('app.frontend_url'), '/').'/admin/project-requests'
        );
    }

    /**
     * Teks dari pengunjung dimasukkan sebagai teks biasa: tanpa tag HTML dan
     * tanpa karakter yang akan dibaca sebagai markdown oleh templat email.
     */
    private function plain(string $text, int $max = 200): string
    {
        $text = trim(strip_tags($text));
        $text = mb_substr($text, 0, $max);

        return addcslashes($text, '*_`[]()#>\\');
    }
}
