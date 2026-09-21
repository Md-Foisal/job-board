<?php

namespace App\Support;

use App\Enums\DocumentType;

/**
 * What each kind of document a candidate keeps has to be, in one place,
 * so the validation, the file picker and the hint under the field agree.
 *
 * The limits follow what established hiring tools accept:
 *
 * - CV: PDF or Word up to 5 MB, the same as Workable and LinkedIn.
 *   Paper size (A4 or Letter) is deliberately not checked: no major board
 *   does, a CV is A4 in most of the world and Letter in North America,
 *   and a Word file has no fixed page size until someone prints it.
 * - Work sample: up to 10 MB, since a portfolio piece can be a zip or a
 *   high-resolution image.
 * - Certificate: PDF or image up to 5 MB.
 *
 * How many are kept follows LinkedIn for CVs: the four most recent stay
 * and an older one leaves the library by itself, so nobody has to tidy up
 * before applying. Leaving the library is a soft delete, so a CV already
 * sent with an application is still exactly what the employer received.
 * Work samples and certificates are kept on purpose, so they are capped
 * rather than rotated.
 */
final class DocumentUploads
{
    public const RECENT_CVS_KEPT = 4;

    public const MAX_OTHER_DOCUMENTS_PER_TYPE = 10;

    /**
     * @var array<string, array{extensions: array<int, string>, max_kilobytes: int}>
     */
    private const SPECS = [
        'cv' => ['extensions' => ['pdf', 'doc', 'docx'], 'max_kilobytes' => 5120],
        'work_sample' => ['extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'], 'max_kilobytes' => 10240],
        'certificate' => ['extensions' => ['pdf', 'jpg', 'jpeg', 'png'], 'max_kilobytes' => 5120],
    ];

    /**
     * @return array<int, string>
     */
    public static function rules(DocumentType $type): array
    {
        $spec = self::SPECS[$type->value];

        return ['required', 'file', 'mimes:'.implode(',', $spec['extensions']), 'max:'.$spec['max_kilobytes']];
    }

    public static function accept(DocumentType $type): string
    {
        return implode(',', array_map(fn (string $extension) => '.'.$extension, self::SPECS[$type->value]['extensions']));
    }

    public static function hint(DocumentType $type): string
    {
        $mb = self::SPECS[$type->value]['max_kilobytes'] / 1024;

        return match ($type) {
            DocumentType::Cv => __('PDF or Word, up to :mb MB. Your :count most recent CVs are kept.', ['mb' => $mb, 'count' => self::RECENT_CVS_KEPT]),
            DocumentType::WorkSample => __('PDF, Word, image or zip, up to :mb MB.', ['mb' => $mb]),
            DocumentType::Certificate => __('PDF or image, up to :mb MB.', ['mb' => $mb]),
        };
    }
}
