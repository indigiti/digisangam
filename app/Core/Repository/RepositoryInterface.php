<?php
declare(strict_types=1);

namespace DigiSangam\Core\Repository;

interface RepositoryInterface
{
    public function all(): array;
    public function find(string $id): ?array;
    public function save(array $record): array;
    public function delete(string $id): bool;
}
