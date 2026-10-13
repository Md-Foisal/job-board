<?php

/*
|--------------------------------------------------------------------------
| Plans
|--------------------------------------------------------------------------
|
| How many times a month each plan may run each AI feature, keyed by the
| AiFeature value. A feature missing from a plan is treated as 0, and
| there is deliberately no way to say "unlimited": every AI call costs
| money, so every plan has a ceiling.
|
| The free plan runs no AI at all; free users get the product's own
| algorithms instead. Paid plans are added here when billing exists.
|
| Review screening is not listed: it is the platform moderating itself,
| paid for by the platform, and never counted against anyone's plan.
|
| The "local" plan is for trying the AI features on a developer's machine
| (PLAN_DEFAULT=local). Production always runs the free plan, whatever
| the environment says.
|
*/

return [

    'default' => env('APP_ENV') === 'production' ? 'free' : env('PLAN_DEFAULT', 'free'),

    'limits' => [

        'free' => [
            'resume_parser' => 0,
            'match_explanation' => 0,
            'cv_builder' => 0,
            'job_post_review' => 0,
        ],

        'local' => [
            'resume_parser' => 5,
            'match_explanation' => 10,
            'cv_builder' => 5,
            'job_post_review' => 5,
        ],

    ],

];
