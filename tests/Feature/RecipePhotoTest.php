<?php

declare(strict_types=1);

use App\Actions\Recipes\DeleteRecipePhoto;
use App\Actions\Recipes\DeleteUserRecipePhotos;
use App\Actions\Recipes\SaveRecipe;
use App\Actions\Recipes\StoreRecipePhoto;
use App\Models\Recipe;
use App\Models\User;
use App\Support\ExifOrientation;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * @return array<string, mixed>
 */
function photoRecipe(array $overrides = []): array
{
    return array_merge([
        'title' => 'Pannenkoeken',
        'steps' => ['Bak ze'],
        'preparation_minutes' => 30,
        'servings' => 4,
        'houdbaar' => false,
    ], $overrides);
}

/**
 * @return list<string>
 */
function storedPhotos(): array
{
    return Storage::disk('local')->allFiles();
}

function jpegBytes(int $width = 40, int $height = 20): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}

function pngWithDeclaredSize(int $width, int $height, bool $withData = false): string
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n".$chunk('IHDR', $ihdr).($withData ? $chunk('IDAT', 'garbage') : '').$chunk('IEND', '');
}

function uploadOf(string $bytes, string $name): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upl');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, null, null, true);
}

function recipeWithPhoto(User $user, string $name = 'old.jpg'): Recipe
{
    $path = "recipe-photos/{$user->id}/{$name}";
    Storage::disk('local')->put($path, jpegBytes());

    return Recipe::factory()->for($user)->create(['photo_path' => $path]);
}

// Guests and ownership

it('redirects a guest away from the photo route', function () {
    $this->get('/recepten/1/foto')->assertRedirect(route('login'));
});

it('answers 404 for another user’s photo and leaves the file alone', function () {
    $recipe = recipeWithPhoto(User::factory()->create());

    actingAs(User::factory()->create())->get(route('recipes.photo', $recipe))->assertNotFound();

    expect(storedPhotos())->toHaveCount(1);
});

it('answers 404 for another user’s recipe on update with a photo, and for show', function () {
    $recipe = recipeWithPhoto(User::factory()->create());
    $other = User::factory()->create();

    actingAs($other)->post("/recepten/{$recipe->id}", photoRecipe(['_method' => 'put', 'photo' => UploadedFile::fake()->image('a.png')]))->assertNotFound();
    actingAs($other)->post("/recepten/{$recipe->id}", photoRecipe(['_method' => 'put', 'remove_photo' => '1']))->assertNotFound();
    actingAs($other)->get(route('recipes.show', $recipe))->assertNotFound();

    expect(storedPhotos())->toBe(["recipe-photos/{$recipe->user_id}/old.jpg"]);
    assertDatabaseHas('recipes', ['id' => $recipe->id, 'photo_path' => "recipe-photos/{$recipe->user_id}/old.jpg"]);
});

// Serving

it('serves the owner’s photo as a private, non-sniffable jpeg', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user);

    $response = actingAs($user)->get(route('recipes.photo', $recipe))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline');
});

it('answers 404 when the recipe has no photo', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    actingAs($user)->get(route('recipes.photo', $recipe))->assertNotFound();
});

it('answers 404 when the photo file is missing from disk', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['photo_path' => "recipe-photos/{$user->id}/gone.jpg"]);

    actingAs($user)->get(route('recipes.photo', $recipe))->assertNotFound();
});

it('exposes only a version, never the stored path', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user, 'abc123.jpg');
    $plain = Recipe::factory()->for($user)->create();

    actingAs($user)->get(route('recipes.show', $recipe))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recipe.photo_version', 'abc123')
            ->missing('recipe.photo_path'));
    actingAs($user)->get(route('recipes.show', $plain))
        ->assertInertia(fn (Assert $page) => $page->where('recipe.photo_version', null)->missing('recipe.photo_path'));
    actingAs($user)->get(route('recipes.index'))
        ->assertInertia(fn (Assert $page) => $page->has('recipes.data.0.photo_version')->missing('recipes.data.0.photo_path'));
});

// Upload

it('stores an uploaded photo under a generated name and re-encodes it as a bounded jpeg', function () {
    $user = User::factory()->create();

    actingAs($user)->post('/recepten', photoRecipe(['photo' => UploadedFile::fake()->image('Mijn foto.png', 1700, 850)]))
        ->assertRedirect();

    $recipe = Recipe::query()->firstOrFail();
    $files = storedPhotos();

    expect($files)->toHaveCount(1)
        ->and($recipe->photo_path)->toBe($files[0])
        ->and($files[0])->toMatch("#^recipe-photos/{$user->id}/[0-9a-z]{26}\\.jpg$#");

    $size = getimagesizefromstring(Storage::disk('local')->get($files[0]) ?? '');
    expect($size)->toBeArray()
        ->and($size[0])->toBe(1600)
        ->and($size[1])->toBe(800)
        ->and($size['mime'])->toBe('image/jpeg');
});

it('does not upscale small photos', function () {
    actingAs(User::factory()->create())->post('/recepten', photoRecipe(['photo' => UploadedFile::fake()->image('a.jpg', 100, 50)]));

    $size = getimagesizefromstring(Storage::disk('local')->get(storedPhotos()[0]) ?? '');
    expect($size[0] ?? null)->toBe(100)->and($size[1] ?? null)->toBe(50);
});

it('accepts jpeg, png and webp by content', function (string $name, string $function) {
    $image = imagecreatetruecolor(10, 10);
    ob_start();
    $function($image);

    actingAs(User::factory()->create())->post('/recepten', photoRecipe(['photo' => uploadOf((string) ob_get_clean(), $name)]))
        ->assertSessionHasNoErrors();

    expect(storedPhotos())->toHaveCount(1);
})->with([
    'jpeg' => ['a.jpg', 'imagejpeg'],
    'png' => ['a.png', 'imagepng'],
    'webp' => ['a.webp', 'imagewebp'],
]);

it('flattens transparency onto white', function () {
    $image = imagecreatetruecolor(10, 10);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
    ob_start();
    imagepng($image);

    actingAs(User::factory()->create())->post('/recepten', photoRecipe(['photo' => uploadOf((string) ob_get_clean(), 'a.png')]));

    $stored = imagecreatefromstring(Storage::disk('local')->get(storedPhotos()[0]) ?? '');
    expect($stored)->not->toBeFalse();
    $rgb = imagecolorat($stored, 5, 5);
    expect($rgb >> 16 & 0xFF)->toBeGreaterThan(240);
});

it('strips EXIF metadata and applies the orientation', function () {
    $jpeg = jpegBytes(40, 20);
    $exif = "Exif\0\0MM\0*\0\0\0\x08\0\x02\x01\x12\0\x03\0\0\0\x01\0\x06\0\0\x87\x69\0\x04\0\0\0\x01GPSX\0\0\0\0";
    $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

    actingAs(User::factory()->create())->post('/recepten', photoRecipe(['photo' => uploadOf($withExif, 'a.jpg')]));

    $stored = Storage::disk('local')->get(storedPhotos()[0]) ?? '';
    $size = getimagesizefromstring($stored);
    expect($size[0] ?? null)->toBe(20)->and($size[1] ?? null)->toBe(40)
        ->and($stored)->not->toContain('Exif')->not->toContain('GPSX');
});

it('keeps the photo form tolerant when the photo field is empty', function () {
    actingAs(User::factory()->create())->post('/recepten', photoRecipe(['photo' => '', 'remove_photo' => '0']))
        ->assertSessionHasNoErrors();

    expect(storedPhotos())->toBe([])
        ->and(Recipe::query()->firstOrFail()->photo_path)->toBeNull();
});

// Adversarial input

it('rejects invalid photos with a Dutch message and stores nothing', function (callable $make, string $message) {
    $response = actingAs(User::factory()->create())->post('/recepten', photoRecipe(['photo' => $make()]));

    $response->assertSessionHasErrors('photo');
    expect(session('errors')->get('photo')[0])->toBe($message)
        ->and(storedPhotos())->toBe([])
        ->and(Recipe::query()->count())->toBe(0);
})->with([
    'empty file' => [fn () => uploadOf('', 'empty.jpg'), 'De foto moet een jpeg-, png- of webp-afbeelding zijn.'],
    'text with image extension' => [fn () => uploadOf('hello', 'a.jpg'), 'De foto moet een jpeg-, png- of webp-afbeelding zijn.'],
    'php with image extension' => [fn () => uploadOf('<?php system($_GET["c"]);', 'shell.php.jpg'), 'De foto moet een jpeg-, png- of webp-afbeelding zijn.'],
    'svg' => [fn () => uploadOf('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'a.svg'), 'De foto moet een jpeg-, png- of webp-afbeelding zijn.'],
    'html as png' => [fn () => uploadOf('<html><script>alert(1)</script></html>', 'a.png'), 'De foto moet een jpeg-, png- of webp-afbeelding zijn.'],
    'gif' => [fn () => uploadOf(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true) ?: '', 'a.gif'), 'De foto moet een jpeg-, png- of webp-afbeelding zijn.'],
    'over 5 MB' => [fn () => UploadedFile::fake()->image('big.jpg', 20, 20)->size(5121), 'De foto mag maximaal 5 MB groot zijn.'],
    'declared dimensions too large' => [fn () => uploadOf(pngWithDeclaredSize(60000, 60000), 'bomb.png'), 'De foto mag maximaal 4000 bij 4000 pixels groot zijn.'],
    'wider than allowed' => [fn () => uploadOf(pngWithDeclaredSize(4001, 1), 'wide.png'), 'De foto mag maximaal 4000 bij 4000 pixels groot zijn.'],
    'corrupt image data' => [fn () => uploadOf(pngWithDeclaredSize(10, 10, true), 'corrupt.png'), 'Deze foto kon niet worden verwerkt. Kies een andere afbeelding.'],
    'failed upload' => [fn () => new UploadedFile(tempnam(sys_get_temp_dir(), 'upl') ?: '', 'a.jpg', null, UPLOAD_ERR_INI_SIZE, true), 'Het uploaden van de foto is mislukt.'],
]);

it('rejects the wrong field types', function (string $kind) {
    $value = match ($kind) {
        'array of files' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')],
        'string' => 'pannenkoek.jpg',
        'nested array' => ['x' => 'y'],
    };

    actingAs(User::factory()->create())->post('/recepten', photoRecipe(['photo' => $value]))
        ->assertSessionHasErrors('photo');

    expect(storedPhotos())->toBe([])->and(Recipe::query()->count())->toBe(0);
})->with(['array of files', 'string', 'nested array']);

it('accepts a real image with a hostile client file name and never uses that name', function (string $name) {
    $user = User::factory()->create();

    actingAs($user)->post('/recepten', photoRecipe(['photo' => UploadedFile::fake()->image($name, 20, 20)]))
        ->assertSessionHasNoErrors();

    $files = storedPhotos();
    expect($files)->toHaveCount(1)
        ->and($files[0])->toMatch("#^recipe-photos/{$user->id}/[0-9a-z]{26}\\.jpg$#");
})->with([
    'double extension' => ['shell.php.jpg'],
    'traversal' => ['../../../etc/passwd.png'],
    'windows traversal' => ['..\\..\\boot.png'],
    'unicode' => ['fōtö-😀-é.png'],
]);

it('rejects a malformed remove flag', function () {
    actingAs(User::factory()->create())->post('/recepten', photoRecipe(['remove_photo' => 'maybe']))
        ->assertSessionHasErrors('remove_photo');
});

// Replace and remove

it('replaces the photo and deletes the old file', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user);

    actingAs($user)->post("/recepten/{$recipe->id}", photoRecipe(['_method' => 'put', 'photo' => UploadedFile::fake()->image('new.png')]))
        ->assertRedirect(route('recipes.show', $recipe));

    $files = storedPhotos();
    expect($files)->toHaveCount(1)
        ->and($files[0])->not->toBe("recipe-photos/{$user->id}/old.jpg")
        ->and($recipe->fresh()?->photo_path)->toBe($files[0]);
});

it('lets the upload win when remove is also ticked', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user);

    actingAs($user)->post("/recepten/{$recipe->id}", photoRecipe(['_method' => 'put', 'remove_photo' => '1', 'photo' => UploadedFile::fake()->image('new.png')]));

    expect(storedPhotos())->toHaveCount(1)
        ->and($recipe->fresh()?->photo_path)->not->toBeNull()->not->toEndWith('old.jpg');
});

it('removes the photo and its file on request', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user);

    actingAs($user)->post("/recepten/{$recipe->id}", photoRecipe(['_method' => 'put', 'remove_photo' => '1']));

    expect(storedPhotos())->toBe([])->and($recipe->fresh()?->photo_path)->toBeNull();
});

it('keeps the photo when an update sends no photo fields', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user);

    actingAs($user)->put("/recepten/{$recipe->id}", photoRecipe(['title' => 'Nieuwe titel']));

    expect(storedPhotos())->toHaveCount(1)
        ->and($recipe->fresh()?->photo_path)->toEndWith('old.jpg');
});

it('keeps the old photo and stores no new file when other fields fail validation', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user);

    actingAs($user)->post("/recepten/{$recipe->id}", photoRecipe(['_method' => 'put', 'title' => '', 'photo' => UploadedFile::fake()->image('new.png')]))
        ->assertSessionHasErrors('title');

    expect(storedPhotos())->toBe(["recipe-photos/{$user->id}/old.jpg"])
        ->and($recipe->fresh()?->photo_path)->toEndWith('old.jpg');
});

it('keeps the old photo and removes the new file when the database save fails', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user);
    Recipe::saved(fn () => throw new RuntimeException('boom'));

    expect(fn () => (new SaveRecipe)->handle(
        user: $user,
        title: 'X',
        steps: [],
        preparationMinutes: 5,
        servings: 2,
        houdbaar: false,
        ingredients: [],
        recipe: $recipe,
        photo: UploadedFile::fake()->image('new.png'),
    ))->toThrow(RuntimeException::class, 'boom');

    expect(storedPhotos())->toBe(["recipe-photos/{$user->id}/old.jpg"])
        ->and($recipe->fresh()?->photo_path)->toEndWith('old.jpg');
});

it('leaves the recipe untouched when the file cannot be written', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user);
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->andReturn(false);
    Storage::set('local', $disk);

    expect(fn () => (new SaveRecipe)->handle(
        user: $user,
        title: 'Gewijzigd',
        steps: [],
        preparationMinutes: 5,
        servings: 2,
        houdbaar: false,
        ingredients: [],
        recipe: $recipe,
        photo: UploadedFile::fake()->image('new.png'),
    ))->toThrow(RuntimeException::class);

    assertDatabaseHas('recipes', ['id' => $recipe->id, 'title' => $recipe->title]);
});

// Delete

it('deletes the photo file together with the recipe', function () {
    $user = User::factory()->create();
    $recipe = recipeWithPhoto($user);

    actingAs($user)->delete(route('recipes.destroy', $recipe))->assertRedirect(route('recipes.index'));

    expect(storedPhotos())->toBe([]);
});

it('deletes a recipe without a photo', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    actingAs($user)->delete(route('recipes.destroy', $recipe))->assertRedirect();

    expect(Recipe::query()->count())->toBe(0);
});

it('deletes every photo of a user when the account is deleted', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    recipeWithPhoto($user);
    recipeWithPhoto($other);

    actingAs($user)->delete(route('profile.destroy'))->assertRedirect('/');

    expect(storedPhotos())->toBe(["recipe-photos/{$other->id}/old.jpg"]);
});

// Actions

it('ignores a missing path when deleting a photo', function () {
    (new DeleteRecipePhoto)->handle(null);
    (new DeleteRecipePhoto)->handle('recipe-photos/1/none.jpg');

    expect(storedPhotos())->toBe([]);
});

it('deletes the photo directory of a user directly', function () {
    $user = User::factory()->create();
    recipeWithPhoto($user);

    (new DeleteUserRecipePhotos)->handle($user);

    expect(storedPhotos())->toBe([]);
});

it('refuses an undecodable image when called directly', function () {
    expect(fn () => (new StoreRecipePhoto)->handle(User::factory()->create(), uploadOf('nope', 'a.jpg')))
        ->toThrow(ValidationException::class);
});

// EXIF orientation

it('reads the orientation from big and little endian EXIF and defaults to 1', function (string $segment, int $expected) {
    $jpeg = jpegBytes();
    $bytes = $segment === '' ? $jpeg : substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($segment) + 2).$segment.substr($jpeg, 2);

    expect(ExifOrientation::read($bytes))->toBe($expected);
})->with([
    'none' => ['', 1],
    'big endian 6' => ["Exif\0\0MM\0*\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01\0\x06\0\0\0\0\0\0", 6],
    'little endian 8' => ["Exif\0\0II*\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0\x08\0\0\0\0\0\0", 8],
    'out of range value' => ["Exif\0\0MM\0*\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01\0\x09\0\0\0\0\0\0", 1],
    'truncated' => ["Exif\0\0MM\0*\0\0\0\x08\0\x05", 1],
    'unknown byte order' => ["Exif\0\0XX\0*\0\0\0\x08\0\x01", 1],
    'bad tiff magic' => ["Exif\0\0MM\0+\0\0\0\x08\0\x01", 1],
    'other app segment' => ["JFIF\0\0\0\0\0\0\0\0", 1],
    'ifd without orientation' => ["Exif\0\0MM\0*\0\0\0\x08\0\x01\x01\x0f\0\x03\0\0\0\x01\0\x06\0\0\0\0\0\0", 1],
]);

it('reads no orientation from something that is not a jpeg', function () {
    expect(ExifOrientation::read('not a jpeg'))->toBe(1)
        ->and(ExifOrientation::read("\xFF\xD8\xFF\xDA"))->toBe(1)
        ->and(ExifOrientation::read("\xFF\xD8\x00\x00"))->toBe(1);
});

it('maps every EXIF orientation onto the pixels', function (int $orientation, array $expected) {
    $image = imagecreatetruecolor(3, 2);
    foreach ([[0, 0, 10], [1, 0, 20], [2, 0, 30], [0, 1, 40], [1, 1, 50], [2, 1, 60]] as [$x, $y, $blue]) {
        imagesetpixel($image, $x, $y, $blue);
    }

    $result = ExifOrientation::apply($image, $orientation);

    $grid = [];
    for ($y = 0; $y < imagesy($result); $y++) {
        for ($x = 0; $x < imagesx($result); $x++) {
            $grid[$y][] = imagecolorat($result, $x, $y) & 0xFF;
        }
    }
    expect($grid)->toBe($expected);
})->with([
    'normal' => [1, [[10, 20, 30], [40, 50, 60]]],
    'mirror horizontal' => [2, [[30, 20, 10], [60, 50, 40]]],
    'rotate 180' => [3, [[60, 50, 40], [30, 20, 10]]],
    'mirror vertical' => [4, [[40, 50, 60], [10, 20, 30]]],
    'transpose' => [5, [[10, 40], [20, 50], [30, 60]]],
    'rotate 90 clockwise' => [6, [[40, 10], [50, 20], [60, 30]]],
    'transverse' => [7, [[60, 30], [50, 20], [40, 10]]],
    'rotate 90 counter clockwise' => [8, [[30, 60], [20, 50], [10, 40]]],
]);
