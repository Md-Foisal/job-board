<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;

/**
 * Both match() expressions in these enums are deliberately written
 * without a default arm, so a newly added case throws instead of
 * silently rendering a blank label or the wrong badge colour. These two
 * tests are what turns "it throws" into "it throws in CI, before
 * anyone sees the page".
 */
test('every application outcome has a label and a badge colour', function () {
    foreach (ApplicationOutcomeStatus::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty();
        expect($status->color())->toBeString()->not->toBeEmpty();
    }
});

test('every application stage has a label', function () {
    foreach (ApplicationStage::cases() as $stage) {
        expect($stage->label())->toBeString()->not->toBeEmpty();
    }
});
