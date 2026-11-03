<?php

declare(strict_types=1);

namespace App\Support;

use GdImage;

final class ExifOrientation
{
    private const TAG = 0x0112;

    public static function read(string $jpeg): int
    {
        if (! str_starts_with($jpeg, "\xFF\xD8")) {
            return 1;
        }

        $offset = 2;

        while (($jpeg[$offset] ?? '') === "\xFF") {
            $marker = ord($jpeg[$offset + 1] ?? "\0");
            $length = self::unpack('n', $jpeg, $offset + 2) ?? 0;

            if ($marker === 0xDA || $length < 2) {
                return 1;
            }

            if ($marker === 0xE1 && substr($jpeg, $offset + 4, 6) === "Exif\0\0") {
                return self::fromTiff(substr($jpeg, $offset + 10, $length - 8));
            }

            $offset += 2 + $length;
        }

        return 1;
    }

    public static function apply(GdImage $image, int $orientation): GdImage
    {
        $flipped = match ($orientation) {
            2, 5, 7 => IMG_FLIP_HORIZONTAL,
            4 => IMG_FLIP_VERTICAL,
            default => null,
        };
        $angle = match ($orientation) {
            3 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        $result = $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);

        if ($flipped !== null) {
            imageflip($result, $flipped);
        }

        return $result;
    }

    private static function fromTiff(string $tiff): int
    {
        $format = match (substr($tiff, 0, 2)) {
            'MM' => ['n', 'N'],
            'II' => ['v', 'V'],
            default => null,
        };

        if ($format === null || self::unpack($format[0], $tiff, 2) !== 42) {
            return 1;
        }

        $directory = self::unpack($format[1], $tiff, 4) ?? 0;
        $entries = self::unpack($format[0], $tiff, $directory) ?? 0;

        for ($i = 0; $i < $entries; $i++) {
            $entry = $directory + 2 + $i * 12;

            if (self::unpack($format[0], $tiff, $entry) === self::TAG) {
                $value = self::unpack($format[0], $tiff, $entry + 8);

                return $value !== null && $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    private static function unpack(string $format, string $data, int $offset): ?int
    {
        $size = in_array($format, ['n', 'v'], true) ? 2 : 4;

        if ($offset < 0 || $offset + $size > strlen($data)) {
            return null;
        }

        $values = unpack($format.'value', $data, $offset);

        return is_array($values) && is_int($values['value'] ?? null) ? $values['value'] : null;
    }
}
