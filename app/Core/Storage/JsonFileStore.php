<?php
declare(strict_types=1);

namespace DigiSangam\Core\Storage;

final class JsonFileStore
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function read(string $relativePath, array $fallback = []): array
    {
        $path = $this->resolve($relativePath);

        if (!is_file($path)) {
            return $fallback;
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            return $fallback;
        }

        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    public function write(string $relativePath, array $payload): void
    {
        $path = $this->resolve($relativePath);
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create storage directory.');
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));

        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write temporary storage file.');
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to atomically replace storage file.');
        }
    }

    private function resolve(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('..', '', $relativePath), '/\\');
        return rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . $relativePath;
    }
}
