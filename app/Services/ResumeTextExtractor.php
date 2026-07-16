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
     * Extract and bound text from a stored PDF using pdftotext (Poppler).
     */
    public function extract(string $path): ResumeTextExtractionResult
    {
        $disk = Storage::disk('r2-private');

        if (! $disk->exists($path)) {
            throw new RuntimeException('Resume PDF could not be read from storage.');
        }

        $resumeStream = $disk->readStream($path);
        $temporaryFile = tmpfile();

        if (! is_resource($resumeStream) || ! is_resource($temporaryFile)) {
            if (is_resource($resumeStream)) {
                fclose($resumeStream);
            }

            if (is_resource($temporaryFile)) {
                fclose($temporaryFile);
            }

            throw new RuntimeException('Resume PDF could not be read from storage.');
        }

        $temporaryPath = stream_get_meta_data($temporaryFile)['uri'] ?? null;

        if (! is_string($temporaryPath)
            || stream_copy_to_stream($resumeStream, $temporaryFile) === false
            || ! fflush($temporaryFile)) {
            fclose($resumeStream);
            fclose($temporaryFile);

            throw new RuntimeException('Resume PDF could not be read from storage.');
        }

        fclose($resumeStream);
        $binPath = config('assessment.resume.pdftotext_bin');
        $configuredBin = is_string($binPath) && $binPath !== '' ? $binPath : null;

        try {
            $text = (new Pdf($configuredBin))
                ->setPdf($temporaryPath)
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
        } finally {
            fclose($temporaryFile);
        }

        $text = preg_replace('/\s+/', ' ', $text) ?: '';

        return ResumeTextExtractionResult::fromNormalizedText(trim($text));
    }
}
