<?php
namespace mod_oralinterview\local;

class utils {
    public static function mask_nationalid(?string $value): string {
        $value = trim((string)$value);
        $length = strlen($value);
        if ($length === 0) {
            return '';
        }
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        return substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . substr($value, -2);
    }
}
