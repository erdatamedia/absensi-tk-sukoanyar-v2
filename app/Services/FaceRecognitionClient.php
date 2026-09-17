<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceRecognitionClient
{
    public function __construct(private ?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl ?? config('services.face_recognition.url');
    }

    /**
     * @return array{embedding: array<float>, confidence: float}|null
     */
    public function embed(string $imageBase64): ?array
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->baseUrl}/embed", ['image' => $imageBase64]);
        } catch (\Throwable $e) {
            Log::warning('Face service tidak bisa diakses saat embed.', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->status() === 422) {
            return null;
        }

        if (! $response->successful()) {
            Log::warning('Face service mengembalikan error saat embed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * @param  Collection<int, array{id: int, embedding: array<float>}>  $candidates
     * @return array{siswa_id: int|null, score: float|null}|null
     */
    public function recognize(string $imageBase64, Collection $candidates): ?array
    {
        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/recognize", [
                'image' => $imageBase64,
                'candidates' => $candidates->values()->all(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Face service tidak bisa diakses saat recognize.', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Face service mengembalikan error saat recognize.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json();
    }
}
