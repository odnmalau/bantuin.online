<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\PdfToText\Exceptions\BinaryNotFoundException;
use Spatie\PdfToText\Exceptions\CouldNotExtractText;
use Spatie\PdfToText\Exceptions\PdfNotFound;
use Spatie\PdfToText\Pdf;

class ResumeTextExtractor
{
    /**
     * Extract text from a stored PDF using pdftotext (Poppler).
     */
    public function extract(string $path, string $disk = 'local'): string
    {
        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('Resume PDF could not be read from storage.');
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $binPath = config('assessment.resume.pdftotext_bin');
        $configuredBin = is_string($binPath) && $binPath !== '' ? $binPath : null;

        try {
            $text = (new Pdf($configuredBin))
                ->setPdf($absolutePath)
                ->text();
        } catch (PdfNotFound) {
            throw new RuntimeException('Resume PDF could not be read from storage.');
        } catch (BinaryNotFoundException $exception) {
            throw new RuntimeException(
                'PDF text extraction is not configured: pdftotext binary not found.',
                previous: $exception,
            );
        } catch (CouldNotExtractText $exception) {
            throw new RuntimeException('Resume PDF text could not be extracted.', previous: $exception);
        }

        $text = preg_replace('/\s+/', ' ', $text) ?: '';

        return trim($text);
    }
}
