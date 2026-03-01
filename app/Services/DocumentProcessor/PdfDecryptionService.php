<?php

namespace App\Services\DocumentProcessor;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class PdfDecryptionService
{
    /**
     * Check if the qpdf CLI tool is available for decryption.
     */
    public function isQpdfAvailable(): bool
    {
        $result = Process::run('qpdf --version');

        return $result->successful();
    }

    /**
     * Detect if a PDF is password-protected by checking for /Encrypt in the raw bytes.
     *
     * This is a PHP-native check that does not require qpdf.
     */
    public function isPasswordProtected(string $storagePath): bool
    {
        $absolutePath = Storage::disk('local')->path($storagePath);

        if (! file_exists($absolutePath)) {
            return false;
        }

        $content = file_get_contents($absolutePath);

        if ($content === false) {
            return false;
        }

        return str_contains($content, '/Encrypt');
    }

    /**
     * Decrypt a password-protected PDF and return the new storage path.
     *
     * @throws \RuntimeException When decryption fails
     */
    public function decrypt(string $storagePath, string $password): string
    {
        $absolutePath = Storage::disk('local')->path($storagePath);
        $decryptedPath = $this->buildDecryptedPath($storagePath);
        $absoluteDecryptedPath = Storage::disk('local')->path($decryptedPath);

        $result = Process::timeout(60)->run(
            "qpdf --password={$this->escapeArgument($password)} --decrypt {$this->escapePath($absolutePath)} {$this->escapePath($absoluteDecryptedPath)}"
        );

        if ($result->failed()) {
            throw new \RuntimeException(
                "Failed to decrypt PDF: {$result->errorOutput()}"
            );
        }

        return $decryptedPath;
    }

    private function buildDecryptedPath(string $storagePath): string
    {
        $info = pathinfo($storagePath);
        $dir = $info['dirname'] ?? '';
        $name = $info['filename'];
        $ext = $info['extension'] ?? 'pdf';

        return "{$dir}/{$name}_decrypted.{$ext}";
    }

    private function escapePath(string $path): string
    {
        return escapeshellarg($path);
    }

    private function escapeArgument(string $argument): string
    {
        return escapeshellarg($argument);
    }
}
