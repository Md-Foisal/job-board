<?php

use App\Actions\StoreCandidateDocument;
use App\Enums\DocumentType;
use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\Document;
use App\Support\DocumentUploads;
use App\Support\ImageUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

    uploadLogo($company, UploadedFile::fake()->image($name, 300, 300))->assertSessionHasNoErrors();

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

    uploadLogo($company, UploadedFile::fake()->image('logo.png', 300, 300)->size(2049))->assertSessionHasErrors('logo');
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

    $picture = UploadedFile::fake()->image('logo.png', 300, 300);
    file_put_contents($picture->getRealPath(), '<?php echo "smuggled"; ?>', FILE_APPEND);

    uploadLogo($company, $picture)->assertSessionHasNoErrors();

    $stored = Storage::disk('public')->get($company->fresh()->logo_path);

    expect($stored)->not->toContain('smuggled')
        ->and(imagecreatefromstring($stored))->not->toBeFalse();
});

test('a picture is stored no larger than it will ever be shown', function (string $field, int $width, int $height, array $stored) {
    Storage::fake('public');
    $company = Company::factory()->create();

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->patch(route('employer.company.update', $company), [
            'name' => $company->name,
            'identity_type' => $company->identity_type->value,
            $field => UploadedFile::fake()->image('picture.jpg', $width, $height),
        ])
        ->assertSessionHasNoErrors();

    $path = $company->fresh()->{$field === 'logo' ? 'logo_path' : 'cover_photo_path'};

    expect(array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2))->toBe($stored);
})->with([
    'a logo' => ['logo', 1600, 1600, [800, 800]],
    'a cover' => ['cover_photo', 3000, 750, [2560, 640]],
]);

test('a picture too small to look sharp is refused, with the reason', function () {
    Storage::fake('public');
    $company = Company::factory()->create();

    uploadLogo($company, UploadedFile::fake()->image('logo.png', 120, 120))->assertSessionHasErrors('logo');

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->patch(route('employer.company.update', $company), [
            'name' => $company->name,
            'identity_type' => $company->identity_type->value,
            'cover_photo' => UploadedFile::fake()->image('cover.png', 800, 200),
        ])
        ->assertSessionHasErrors('cover_photo');
});

test('the upload fields say what they take, from the same numbers the check uses', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->get(route('employer.company.edit', $company))
        ->assertSee(ImageUploads::hint(ImageUploads::LOGO))
        ->assertSee(ImageUploads::hint(ImageUploads::COVER));
});

test('only the most recent CVs stay in the library, and an older one is never lost from an application', function () {
    Storage::fake('local');
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;
    $store = app(StoreCandidateDocument::class);

    $first = $store($profile, DocumentType::Cv, UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'));
    $this->travel(1)->minutes();

    foreach (range(2, DocumentUploads::RECENT_CVS_KEPT + 1) as $i) {
        $store($profile, DocumentType::Cv, UploadedFile::fake()->create("cv-{$i}.pdf", 10, 'application/pdf'));
        $this->travel(1)->minutes();
    }

    expect($profile->documents()->where('document_type', DocumentType::Cv)->count())->toBe(DocumentUploads::RECENT_CVS_KEPT)
        ->and($profile->documents()->find($first->id))->toBeNull()
        ->and(Document::withTrashed()->find($first->id))->not->toBeNull();
});

test('work samples are capped rather than rotated', function () {
    Storage::fake('local');
    $candidate = candidateUser();
    Document::factory()->count(DocumentUploads::MAX_OTHER_DOCUMENTS_PER_TYPE)->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'document_type' => DocumentType::WorkSample,
    ]);

    Livewire::actingAs($candidate)
        ->test('pages::candidate.documents')
        ->set('documentType', DocumentType::WorkSample->value)
        ->set('file', UploadedFile::fake()->create('sample.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('file');

    expect($candidate->candidateProfile->documents()->where('document_type', DocumentType::WorkSample)->count())
        ->toBe(DocumentUploads::MAX_OTHER_DOCUMENTS_PER_TYPE);
});
