<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProductMediaController extends Controller
{
    /**
     * Mengunggah thumbnail, gambar preview, dan file template.
     */
    public function store(Request $request, string $productId): JsonResponse
    {
        $validated = $request->validate([
            'thumbnail' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'preview_images' => [
                'sometimes',
                'array',
                'max:6',
            ],

            'preview_images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'template_file' => [
                'sometimes',
                'file',
                'mimes:zip',
                'extensions:zip',
                'max:102400',
            ],
        ]);

        if (
            ! $request->hasFile('thumbnail') &&
            ! $request->hasFile('preview_images') &&
            ! $request->hasFile('template_file')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu file untuk diunggah.',
            ], 422);
        }

        $product = Product::findOrFail($productId);

        $oldFilePath = $product->file_path;

        $existingPreviewCount = count($product->preview_images ?? []);
        $newPreviewCount = $request->hasFile('preview_images')
        ? count($request->file('preview_images'))
        : 0;

        if ($existingPreviewCount + $newPreviewCount > 10) {
        return response()->json([
            'success' => false,
            'message' => 'Maksimal terdapat 10 gambar preview per produk.',
        ], 422);
        }

        $publicDisk = Storage::disk('public');
        $privateDisk = Storage::disk('template_downloads');

        $newPublicPaths = [];
        $newPrivatePath = null;
        $oldFilePath = $product->file_path;

        try {
            $attributes = [];

            // Upload thumbnail.
            if ($request->hasFile('thumbnail')) {
                $oldThumbnail = $product->thumbnail;

                $thumbnailPath = $request->file('thumbnail')
                    ->store('products/thumbnails', 'public');

                if (! $thumbnailPath) {
                    throw new RuntimeException(
                        'Gagal menyimpan thumbnail.'
                    );
                }

                $newPublicPaths[] = $thumbnailPath;
                $attributes['thumbnail'] = $thumbnailPath;
            }

            // Upload beberapa gambar preview.
            if ($request->hasFile('preview_images')) {
                $previewImages = $product->preview_images ?? [];

                foreach ($request->file('preview_images') as $image) {
                    $path = $image->store(
                        'products/previews',
                        'public'
                    );

                    if (! $path) {
                        throw new RuntimeException(
                            'Gagal menyimpan gambar preview.'
                        );
                    }

                    $newPublicPaths[] = $path;
                    $previewImages[] = $path;
                }

                $attributes['preview_images'] = $previewImages;
            }

            // Upload file ZIP ke penyimpanan privat.
            if ($request->hasFile('template_file')) {
                $file = $request->file('template_file');

                $newFileName = Str::uuid()->toString() . '.zip';

                $path = $file->storeAs(
                    '',
                    $newFileName,
                    'template_downloads'
                );

                if (! $path) {
                    throw new RuntimeException(
                        'Gagal menyimpan file template.'
                    );
                }

                $newPrivatePath = $path;

                $originalName = pathinfo(
                    $file->getClientOriginalName(),
                    PATHINFO_FILENAME
                );

                $safeName = Str::slug($originalName ?: $product->name);

                $attributes['file_path'] = $path;
                $attributes['file_name'] = $safeName . '.zip';
                $attributes['file_size'] = $file->getSize();
            }

            $product->update($attributes);

        } catch (Throwable $e) {
            // Bersihkan file baru jika database gagal diperbarui.
            foreach ($newPublicPaths as $path) {
                $publicDisk->delete($path);
            }

            if ($newPrivatePath) {
                $privateDisk->delete($newPrivatePath);
            }

            throw $e;
        }

        // Hapus thumbnail lama setelah thumbnail baru tersimpan.
        if (
            isset($oldThumbnail) &&
            is_string($oldThumbnail) &&
            str_starts_with($oldThumbnail, 'products/thumbnails/')
        ) {
            $publicDisk->delete($oldThumbnail);
        }

        // Hapus file template lama setelah penggantian berhasil.
        if (
            $newPrivatePath &&
            isset($oldFilePath) &&
            $oldFilePath !== $newPrivatePath &&
            is_string($oldFilePath) &&
            basename($oldFilePath) === $oldFilePath
        ) {
            $privateDisk->delete($oldFilePath);
        }

        $product->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Media produk berhasil diunggah.',
            'data' => [
                'product_id' => $product->id,
                'product_name' => $product->name,

                'thumbnail' => $product->thumbnail,
                'thumbnail_url' => $product->thumbnail
                    ? $publicDisk->url($product->thumbnail)
                    : null,

                'preview_images' => collect(
                    $product->preview_images ?? []
                )->map(function ($path) use ($publicDisk) {
                    return [
                        'path' => $path,
                        'url' => $publicDisk->url($path),
                    ];
                })->values(),

                'template_file' => [
                    'available' => ! empty($product->file_path),
                    'file_name' => $product->file_name,
                    'file_size' => $product->file_size,
                ],
            ],
        ]);
    }
}
