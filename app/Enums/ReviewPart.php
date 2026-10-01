<?php

namespace App\Enums;

/**
 * The two pieces of writing a company review row holds, each read by
 * staff on its own: the applicant's review and the company's answer.
 */
enum ReviewPart: string
{
    case Review = 'review';
    case Response = 'response';

    /**
     * Where the AI's hint for this part is kept on company_reviews.
     */
    public function screeningColumn(): string
    {
        return match ($this) {
            self::Review => 'screening',
            self::Response => 'response_screening',
        };
    }

    /**
     * The grounds the AI may point to: the same closed lists staff decide
     * by, so every hint names something staff could act on.
     *
     * "Clearly false or misleading" is left out for reviews. It applies
     * only when the product's own records contradict a claim, and the AI
     * sees none of them -- from the text alone it could only guess, and a
     * guess about truth is a guess about sentiment.
     *
     * @return array<string, string> value => label
     */
    public function screeningReasons(): array
    {
        $reasons = match ($this) {
            self::Review => array_values(array_filter(
                ReviewRejectionReason::cases(),
                fn (ReviewRejectionReason $reason) => $reason !== ReviewRejectionReason::FalseOrMisleading,
            )),
            self::Response => ResponseRejectionReason::cases(),
        };

        return collect($reasons)
            ->mapWithKeys(fn (ReviewRejectionReason|ResponseRejectionReason $reason) => [$reason->value => $reason->label()])
            ->all();
    }
}
