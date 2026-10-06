<?php
declare(strict_types=1);

namespace DigiSangam\Core\Csv;

final class CsvService
{
    public static function encode(array $rows): string
    {
        if ($rows === []) return '';
        $stream = fopen('php://temp', 'r+');
        $headers = array_keys($rows[0]);
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn($key) => is_scalar($row[$key] ?? null) ? (string)$row[$key] : json_encode($row[$key] ?? null), $headers));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv === false ? '' : $csv;
    }

    public static function decode(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $headers = fgetcsv($stream);
        if (!is_array($headers)) return [];
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            if (count($values) !== count($headers)) continue;
            $rows[] = array_combine($headers, $values);
        }
        fclose($stream);
        return $rows;
    }
}
