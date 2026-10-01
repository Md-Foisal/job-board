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

test('the privacy policy says job views are counted for everyone, and what the count keeps', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('signed in or not, we add one to')
        ->assertSee('The count holds no name, account or address.')
        ->assertSee('which postings it has already been counted for that day');
});

test('the privacy policy names Anthropic as the reader of CVs sent to the AI, and what it keeps', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Filling your profile from your CV')
        ->assertSee('suggest your headline, summary, phone number, location, roles and')
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
        ->assertSee('preferences are never shown to employers, even when you apply, and')
        ->assertSee('neither are the phone number and location on your profile')
        ->assertSee('How you match a job')
        ->assertSee("An employer sees only how many of the job's skills you have", false)
        ->assertSee('name, contact details, photos, links, CVs and salary expectations are')
        ->assertSee('We keep it for a day');
});

test('the privacy policy says what the CV builder keeps and what its AI suggestions send', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Building a CV')
        ->assertSee('The CV builder makes a CV from your profile on our own servers.')
        ->assertSee('profile you ask our AI to explain a match with or to improve the')
        ->assertSee('on our behalf as our processor: your headline, summary, roles with')
        ->assertSee('phone number, location, links, photo and preferences are not sent.')
        ->assertSee('The suggestions wait an hour for you to choose from and are then');
});

test('the privacy policy says what an AI job post review sends, and that no applicant is in it', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Reviewing a job posting')
        ->assertSee('the posting as the company wrote it, and totals for it such')
        ->assertSee('Nothing about any applicant is sent')
        ->assertSee('no names, CVs, answers or individual match scores.');
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

test('the privacy policy says what a company review shows, and who can tell who wrote it', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee("Reviewing a company's hiring process")
        ->assertSee('never your name, the job you applied for, the')
        ->assertSee('The company cannot see who wrote it.')
        ->assertSee('staff who work at that')
        ->assertSee('it says nothing about')
        ->assertSee('The company can publish one answer under your review.')
        ->assertSee('describes you, or threatens you.');
});

test('the privacy policy says what the AI reads of a review or an answer, and what erasing an account does to them', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee("Before our team reads a review or a company's answer, our AI may read", false)
        ->assertSee("this, Anthropic is sent the review's ratings, headline and text and the", false)
        ->assertSee('replies to. Nothing about who wrote either is sent. The AI never')
        ->assertSee('publishes or rejects anything: a person decides every time.')
        ->assertSee('of companies with any answers to them &mdash; and keep only an anonymous', false)
        ->assertSee("stay correct. Answers you wrote to reviews on a company's behalf stay", false);
});

test('the terms of service set out the review rules, and what moderation never does', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('Reviews of companies')
        ->assertSee("You can review a company's hiring process only if you applied to one of", false)
        ->assertSee('never because it is negative or because the company disagrees with it.')
        ->assertSee('as clearly false only when our own records contradict it, such as a')
        ->assertSee('or hide a review, and reporting one does not take it down. An answer');
});
