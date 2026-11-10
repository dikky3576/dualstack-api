<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectRequest;
use App\Notifications\NewProjectRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class ProjectRequestController extends Controller
{
    /**
     * Formulir publik di halaman Jasa.
     */
    public function store(Request $request): JsonResponse
    {
        // Kolom jebakan: manusia tidak melihatnya, bot biasanya mengisinya.
        // Dijawab sukses agar bot tidak tahu ia ditolak.
        if (filled($request->input('website'))) {
            return $this->accepted();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'whatsapp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s()]*$/'],
            'project_type' => ['required', Rule::in(ProjectRequest::TYPES)],
            'description' => ['required', 'string', 'min:20', 'max:3000'],
            'deadline' => ['nullable', 'string', 'max:100'],
            'budget' => ['nullable', 'string', 'max:100'],
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka dan tanda + - ( ).',
            'description.min' => 'Ceritakan sedikit lebih rinci (minimal 20 karakter) agar kami bisa memahami kebutuhanmu.',
        ]);

        $projectRequest = ProjectRequest::create($data);

        $notify = config('services.project_requests.notify_email');

        if (is_string($notify) && $notify !== '') {
            $id = $projectRequest->id;

            // Setelah respons terkirim: SMTP yang lambat tidak menahan pengunjung.
            dispatch(function () use ($id, $notify) {
                try {
                    $saved = ProjectRequest::find($id);

                    if ($saved) {
                        Notification::route('mail', $notify)->notify(new NewProjectRequest($saved));
                    }
                } catch (\Throwable $e) {
                    Log::error('Project request email failed', [
                        'request_id' => $id,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();
        }

        return $this->accepted();
    }

    private function accepted(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Permintaanmu sudah kami terima. Kami akan menghubungi lewat email atau WhatsApp.',
        ], 201);
    }
}
