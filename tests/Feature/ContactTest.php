<?php

use App\Actions\AnonymizeUser;
use App\Enums\ContactTopic;
use App\Filament\Resources\ContactMessages\Pages\ManageContactMessages;
use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use App\Notifications\ContactReply;
use App\Support\SubmissionLimits;
use Filament\Actions\Testing\TestAction;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function contactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nadia Rahman',
        'email' => 'nadia@example.com',
        'topic' => ContactTopic::Account->value,
        'body' => 'I cannot sign in since I changed my phone.',
    ], $overrides);
}

function contactMessage(array $overrides = []): ContactMessage
{
    return ContactMessage::create(contactPayload($overrides));
}

test('anyone can open the contact page, and it says when to expect an answer', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('Contact us - '.config('app.name'))
        ->assertSee('within '.ContactMessage::REPLY_WITHIN_WORKING_DAYS.' working days')
        ->assertSee(route('password.request'), false);
});

test('a signed-in visitor finds their name and address already filled in', function () {
    $rafi = candidateUser();

    $this->actingAs($rafi)
        ->get(route('contact'))
        ->assertSeeHtml('value="'.e($rafi->email).'"')
        ->assertSeeHtml('value="'.e($rafi->name).'"');
});

test('the privacy policy links to the contact page with the topic already chosen', function () {
    $this->get(route('privacy'))->assertSee(route('contact', ['topic' => 'privacy']), false);

    $html = $this->get(route('contact', ['topic' => 'privacy']))->getContent();

    expect($html)->toMatch('/<option[^>]*\\sselected[^>]*value="privacy"/')
        ->not->toMatch('/<option[^>]*\\sselected[^>]*value="account"/');
});

test('a message is kept, staff are told, and the sender sees where the answer will go', function () {
    Notification::fake();
    $staff = staffWithTwoFactor();
    $rafi = candidateUser();

    $this->actingAs($rafi)
        ->post(route('contact.store'), contactPayload())
        ->assertRedirect(route('contact'))
        ->assertSessionHas('contactSentTo', 'nadia@example.com');

    $message = ContactMessage::sole();
    expect($message->topic)->toBe(ContactTopic::Account)
        ->and($message->user_id)->toBe($rafi->id)
        ->and($message->isOpen())->toBeTrue();

    Notification::assertSentTo($staff, ContactMessageReceived::class);
    Notification::assertNotSentTo($rafi, ContactMessageReceived::class);

    $this->get(route('contact'))->assertSee('Message sent')->assertSee('We will reply to nadia@example.com');
});

test('the message, the address and the topic are checked', function () {
    $this->post(route('contact.store'), contactPayload([
        'email' => 'not-an-address',
        'topic' => 'billing',
        'body' => 'Help',
    ]))->assertSessionHasErrors(['email', 'topic', 'body']);

    expect(ContactMessage::count())->toBe(0);
});

test('a script that fills the hidden field is told it worked, and nothing is kept', function () {
    Notification::fake();
    staffWithTwoFactor();

    $this->post(route('contact.store'), contactPayload(['website' => 'https://spam.example']))
        ->assertRedirect(route('contact'))
        ->assertSessionHas('contactSentTo');

    expect(ContactMessage::count())->toBe(0);
    Notification::assertNothingSent();
});

test('one network cannot keep sending messages', function () {
    $key = SubmissionLimits::contactMessageKey('127.0.0.1');
    for ($i = 0; $i < SubmissionLimits::CONTACT_MESSAGES_PER_HOUR; $i++) {
        RateLimiter::hit($key, 3600);
    }

    $this->post(route('contact.store'), contactPayload())->assertSessionHasErrors('body');

    expect(ContactMessage::count())->toBe(0);
});

test('staff reply from the panel: the answer goes to the sender and the message closes', function () {
    Notification::fake();
    $message = contactMessage();
    $staff = staffWithTwoFactor();
    $this->actingAs($staff);

    Livewire::test(ManageContactMessages::class)
        ->assertCanSeeTableRecords([$message])
        ->callAction(TestAction::make('reply')->table($message), data: ['reply' => "Thanks for writing.\n\nYou can now sign in again."]);

    $message->refresh();
    expect($message->isOpen())->toBeFalse()
        ->and($message->status())->toBe('Replied')
        ->and($message->closed_by_id)->toBe($staff->id);

    Notification::assertSentOnDemand(ContactReply::class, function (ContactReply $reply, array $channels, AnonymousNotifiable $notifiable) {
        return $notifiable->routes['mail'] === ['nadia@example.com' => 'Nadia Rahman'];
    });
});

test('staff can close a message without replying', function () {
    Notification::fake();
    $message = contactMessage();
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageContactMessages::class)
        ->callAction(TestAction::make('close')->table($message));

    expect($message->fresh()->status())->toBe('Closed');
    Notification::assertNothingSent();
});

test('the inbox is closed to anyone who is not staff', function () {
    $this->actingAs(candidateUser())
        ->get('/admin/support/messages')
        ->assertForbidden();
});

test('erasing an account deletes the messages it sent, signed in or not', function () {
    $rafi = candidateUser();
    contactMessage(['user_id' => $rafi->id, 'email' => 'other@example.com']);
    contactMessage(['email' => $rafi->email]);
    $stranger = contactMessage(['email' => 'stranger@example.com']);

    app(AnonymizeUser::class)($rafi);

    expect(ContactMessage::pluck('id')->all())->toBe([$stranger->id]);
});

test('a closed message is deleted once it has been kept long enough, an open one never', function () {
    $old = contactMessage();
    $old->forceFill(['closed_at' => now()->subMonths(ContactMessage::KEPT_AFTER_CLOSING_MONTHS)->subDay()])->save();
    $recent = contactMessage();
    $recent->forceFill(['closed_at' => now()->subMonth()])->save();
    $open = contactMessage();
    $open->forceFill(['created_at' => now()->subYears(2)])->save();

    $this->artisan('model:prune', ['--model' => [ContactMessage::class]])->assertSuccessful();

    expect(ContactMessage::pluck('id')->sort()->values()->all())->toBe([$recent->id, $open->id]);
});

test('an error page points to the contact page', function () {
    $this->get('/no-such-page')->assertSee(route('contact'), false);
});
