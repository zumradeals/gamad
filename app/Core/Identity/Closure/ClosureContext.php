<?php

namespace App\Core\Identity\Closure;

/** Ce que les participants se transmettent entre « avant » et « après » la suppression, et les fichiers à effacer après validation. */
final class ClosureContext
{
    /** @var list<array{0: string, 1: string}> */
    private array $files = [];

    /** @var array<string, mixed> */
    private array $memory = [];

    /** Le fichier sera effacé après la validation de la transaction, jamais avant. */
    public function purgeFile(string $disk, ?string $path): void
    {
        if ($path !== null && $path !== '') {
            $this->files[] = [$disk, $path];
        }
    }

    /** @param  iterable<string|null>  $paths */
    public function purgeFiles(string $disk, iterable $paths): void
    {
        foreach ($paths as $path) {
            $this->purgeFile($disk, $path);
        }
    }

    /** @return list<array{0: string, 1: string}> */
    public function files(): array
    {
        return $this->files;
    }

    public function remember(string $key, mixed $value): void
    {
        $this->memory[$key] = $value;
    }

    public function recall(string $key, mixed $default = null): mixed
    {
        return $this->memory[$key] ?? $default;
    }
}
