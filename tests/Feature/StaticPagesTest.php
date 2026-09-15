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
