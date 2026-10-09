<?php

test('the pay asked for is plainly monthly, with the currency first and the year worked out beside it', function () {
    $this->actingAs(candidateUser())
        ->get(route('candidate.preferences.edit'))
        ->assertOk()
        ->assertSeeInOrder(['Currency', 'Pay you want, per month', 'At least, per month', 'Up to, per month'])
        ->assertSee('x-text="yearly"', false)
        ->assertSee('a year.');
});

test('the page is marked private and does not claim that jobs are compared with the date the candidate is free', function () {
    $this->actingAs(candidateUser())
        ->get(route('candidate.preferences.edit'))
        ->assertSee('Private')
        ->assertSee('Only you see these. Each job page compares its pay, workplace and type of work with them.')
        ->assertSee('For your own planning; jobs are not compared with it.');
});
