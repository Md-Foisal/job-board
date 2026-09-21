<?php

use App\Enums\MembershipRole;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function uploadLogo(Company $company, UploadedFile $logo)
{
    return test()->actingAs(employerUser($company, MembershipRole::Owner))
        ->patch(route('employer.company.update', $company), [
            'name' => $company->name,
            'identity_type' => $company->identity_type->value,
            'logo' => $logo,
        ]);
}

test('a picture in a format every browser shows is accepted', function (string $name) {
    Storage::fake('public');
    $company = Company::factory()->create();

    uploadLogo($company, UploadedFile::fake()->image($name))->assertSessionHasNoErrors();

    expect($company->fresh()->logo_path)->not->toBeNull();
})->with(['logo.jpg', 'logo.png', 'logo.webp']);

test('a picture most browsers cannot show, or that can carry script, is refused', function (string $name, string $mime) {
    Storage::fake('public');
    $company = Company::factory()->create();

    uploadLogo($company, UploadedFile::fake()->create($name, 100, $mime))->assertSessionHasErrors('logo');

    expect($company->fresh()->logo_path)->toBeNull();
})->with([
    'an iPhone photo' => ['logo.heic', 'image/heic'],
    'an animated gif' => ['logo.gif', 'image/gif'],
    'an svg' => ['logo.svg', 'image/svg+xml'],
]);

test('renaming a file does not get it past the check', function () {
    Storage::fake('public');
    $company = Company::factory()->create();

    uploadLogo($company, UploadedFile::fake()->create('logo.png', 100, 'application/pdf'))->assertSessionHasErrors('logo');
});

test('a logo over two megabytes is refused', function () {
    Storage::fake('public');
    $company = Company::factory()->create();

    uploadLogo($company, UploadedFile::fake()->image('logo.png')->size(2049))->assertSessionHasErrors('logo');
});

test('a candidate photo follows the same rule', function () {
    Storage::fake('public');
    $candidate = candidateUser();

    $this->actingAs($candidate)
        ->patch(route('candidate.profile.update'), ['avatar' => UploadedFile::fake()->create('me.heic', 100, 'image/heic')])
        ->assertSessionHasErrors('avatar');

    expect($candidate->fresh()->avatar)->toBeNull();
});

test('a private file cannot be fetched by guessing its address', function () {
    Storage::fake('local');
    Storage::disk('local')->put('resumes/someones-cv.pdf', 'private');

    $this->get('/storage/resumes/someones-cv.pdf')->assertForbidden();
});

test('a picture far larger in pixels than any camera takes is refused before it is decoded', function () {
    Storage::fake('public');
    $company = Company::factory()->create();

    uploadLogo($company, UploadedFile::fake()->image('logo.png', 5000, 10))->assertSessionHasErrors('logo');
});

test('a file that claims to be a picture but is not one is refused', function () {
    Storage::fake('public');
    $company = Company::factory()->create();

    uploadLogo($company, UploadedFile::fake()->createWithContent('logo.png', 'not really an image'))->assertSessionHasErrors('logo');
});

test('anything hidden after the picture data is gone once it is stored', function () {
    Storage::fake('public');
    $company = Company::factory()->create();

    $picture = UploadedFile::fake()->image('logo.png', 40, 40);
    file_put_contents($picture->getRealPath(), '<?php echo "smuggled"; ?>', FILE_APPEND);

    uploadLogo($company, $picture)->assertSessionHasNoErrors();

    $stored = Storage::disk('public')->get($company->fresh()->logo_path);

    expect($stored)->not->toContain('smuggled')
        ->and(imagecreatefromstring($stored))->not->toBeFalse();
});
