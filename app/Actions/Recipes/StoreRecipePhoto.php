<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\User;
use App\Support\ExifOrientation;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class StoreRecipePhoto
{
    public const MAX_EDGE = 1600;

    private const QUALITY = 85;

    public static function directory(User $user): string
    {
        return 'recipe-photos/'.$user->id;
    }

    public function handle(User $user, UploadedFile $file): string
    {
        $bytes = $file->getContent();

        // GD warns about truncated or corrupt data; the undecodable case is answered below.
        $source = @imagecreatefromstring($bytes);

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => 'Deze foto kon niet worden verwerkt. Kies een andere afbeelding.']);
        }

        $path = self::directory($user).'/'.Str::lower((string) Str::ulid()).'.jpg';

        if (Storage::disk('local')->put($path, $this->encode(ExifOrientation::apply($source, ExifOrientation::read($bytes)))) === false) {
            throw new RuntimeException('The recipe photo could not be written to disk.');
        }

        return $path;
    }

    private function encode(GdImage $source): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
