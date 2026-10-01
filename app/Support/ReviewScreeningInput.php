<?php

namespace App\Support;

use App\Enums\ReviewPart;
use App\Models\CompanyReview;

/**
 * What the review screener is given to read: the text being checked and
 * the company it is about, and nothing that says who wrote it.
 *
 * A review goes with its ratings. An answer goes with the review it
 * answers, because whether an answer points at the writer or answers
 * something else entirely can only be judged against the review.
 */
final class ReviewScreeningInput
{
    public static function material(CompanyReview $review, ReviewPart $part): string
    {
        $theReview = [
            'title' => $review->title,
            'body' => $review->body,
        ];

        $input = match ($part) {
            ReviewPart::Review => [
                'check' => 'review',
                'company' => $review->company->name,
                'review' => $theReview + [
                    'overall_rating' => $review->overall_rating,
                    'communication_rating' => $review->communication_rating,
                    'job_as_described' => $review->job_as_described->value,
                ],
            ],
            ReviewPart::Response => [
                'check' => 'response',
                'company' => $review->company->name,
                'review' => $theReview,
                'response' => ['body' => $review->response_body],
            ],
        };

        // Whatever the writer or the company typed arrives as a quoted
        // string; angle brackets are escaped as well, so nothing in it can
        // read as markup around it.
        return json_encode($input, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
