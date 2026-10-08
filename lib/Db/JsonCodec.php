<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Db;

class JsonCodec {
    public function encode(array $payload): string {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function decode(?string $payload, array $default = []): array {
        if ($payload === null || trim($payload) === '') {
            return $default;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : $default;
        } catch (\JsonException) {
            return $default;
        }
    }
}
