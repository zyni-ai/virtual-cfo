<?php

namespace App\Services\DocumentProcessor;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class PdfDecryptionService
{
    public const STORAGE_DISK = 'local';

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
        $absolutePath = Storage::disk(self::STORAGE_DISK)->path($storagePath);

        if (! file_exists($absolutePath)) {
            return false;
        }

        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 4096);
        fclose($handle);

        return $header !== false && str_contains($header, '/Encrypt');
    }

    /**
     * Decrypt a password-protected PDF and return the new storage path.
     *
     * @throws \RuntimeException When decryption fails
     */
    public function decrypt(string $storagePath, string $password): string
    {
        $absolutePath = Storage::disk(self::STORAGE_DISK)->path($storagePath);
        $decryptedPath = $this->buildDecryptedPath($storagePath);
        $absoluteDecryptedPath = Storage::disk(self::STORAGE_DISK)->path($decryptedPath);

        $result = Process::timeout(60)->run(
            "qpdf --password={$this->escapeArgument($password)} --decrypt {$this->escapePath($absolutePath)} {$this->escapePath($absoluteDecryptedPath)}"
        );

        // qpdf exit codes: 0 = success, 2 = errors, 3 = warnings (file is valid)
        if (! in_array($result->exitCode(), [0, 3], true)) {
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
