<?php

test('the about page renders for a guest', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertSee('the person applying deserves to know as much as the person');
});

test('the privacy policy renders for a guest', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('What we collect');
});

test('the privacy policy names Anthropic as the reader of CVs sent to the AI, and what it keeps', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Filling your profile from your CV')
        ->assertSee('is sent to Anthropic, which reads it on our behalf')
        ->assertSee('By default it deletes it within 30 days.')
        ->assertSee('it may keep it for up to')
        ->assertSee('https://privacy.claude.com/en/articles/7996868', false)
        ->assertSee('https://privacy.claude.com/en/articles/7996866', false)
        ->assertDontSee('does not keep it');
});

test('the privacy policy says what an AI match explanation sends, to whom, and what it leaves out', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('preferences are never shown to employers, even when you apply.')
        ->assertSee('How you match a job')
        ->assertSee("An employer sees only how many of the job's skills you have", false)
        ->assertSee('name, contact details, photos, links, CVs and salary expectations are')
        ->assertSee('We keep it for a day');
});

test('the terms of service render for a guest', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('One account per person');
});

test('the footer links to all three static pages', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee(route('about'), false);
    $response->assertSee(route('privacy'), false);
    $response->assertSee(route('terms'), false);
});
