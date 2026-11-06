<?php

namespace Tests\Feature\Web;

use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Queues\GuestTicketCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class GuestQueuePageTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Queue $queue;

    private QueueSession $session;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $business = $this->businessOwnedBy($this->owner, ['name' => 'Ahmad Barber']);
        $branch = $this->branchOf($business, ['name' => 'Mazzeh', 'timezone' => 'Asia/Damascus']);
        $this->queue = $this->queueFor($this->serviceOf($branch, ['name' => 'Haircut']), [
            'name' => 'Haircuts',
            'ticket_prefix' => 'HC',
        ]);
        $this->token = $this->queue->public_token_encrypted;
        $this->session = $this->openSession($this->queue, $this->owner);
    }

    private function joinUri(): string
    {
        return '/join/'.$this->token;
    }

    /**
     * Loads the form and returns its nonce (from the cookie it sets).
     */
    private function formNonce(): string
    {
        return $this->get($this->joinUri())->getCookie(GuestTicketCookies::FORM_COOKIE)->getValue();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function submitJoin(array $data = ['name' => 'Rami']): TestResponse
    {
        $nonce = $this->formNonce();

        return $this->withCookie(GuestTicketCookies::FORM_COOKIE, $nonce)
            ->withHeaders(['Sec-Fetch-Site' => 'same-origin'])
            ->post($this->joinUri(), $data + ['_form' => $nonce, '_key' => 'web-form-key-0000000001']);
    }

    // ----- Join page ----------------------------------------------------

    public function test_the_join_page_shows_the_queue_and_a_minimal_form(): void
    {
        $this->ticketIn($this->session, TicketStatus::Waiting, ['guest_phone' => '+963944000111']);

        $response = $this->get($this->joinUri());

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Haircuts')
            ->assertSee('Ahmad Barber')
            ->assertSee('1 person is waiting')
            ->assertSee('name="name"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertDontSee('944000111');

        $form = $response->getCookie(GuestTicketCookies::FORM_COOKIE, false);
        $this->assertTrue($form->isHttpOnly());
        $this->assertSame(Cookie::SAMESITE_STRICT, $form->getSameSite());
    }

    public function test_the_page_speaks_arabic_right_to_left(): void
    {
        $chosen = $this->get($this->joinUri().'?lang=ar');
        $browser = $this->withHeaders(['Accept-Language' => 'ar-SY,ar;q=0.9'])->get($this->joinUri());

        $chosen->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('انضم إلى الدور')
            ->assertCookie('dory_lang', 'ar');
        $browser->assertSee('dir="rtl"', false);
    }

    public function test_an_unknown_or_rotated_link_shows_a_friendly_page(): void
    {
        $this->get('/join/'.str_repeat('x', 40))->assertNotFound()->assertSee('Link not available');
        $this->get('/join/short')->assertNotFound();
    }

    public function test_a_paused_queue_shows_its_state_without_a_form(): void
    {
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'pause'))->assertOk();
        $this->forgetAuthenticatedUser();

        $this->get($this->joinUri())
            ->assertOk()
            ->assertSee('Paused')
            ->assertDontSee('name="name"', false)
            ->assertSee('http-equiv="refresh"', false);
    }

    // ----- Joining ------------------------------------------------------

    public function test_joining_redirects_to_the_ticket_page_with_a_protected_cookie(): void
    {
        $response = $this->submitJoin(['name' => 'Rami']);

        $ticket = QueueTicket::query()->sole();
        $response->assertStatus(303)->assertRedirect('/ticket/'.$ticket->public_id);
        $cookie = $response->getCookie(GuestTicketCookies::name($ticket->public_id), false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());

        // The cookie holds the access token, encrypted; the URL never does.
        $token = $response->getCookie(GuestTicketCookies::name($ticket->public_id))->getValue();
        $this->assertTrue($ticket->accessTokenMatches($token));
        $this->assertNotSame($token, $cookie->getValue());
        $this->assertStringNotContainsString($token, (string) $response->headers->get('Location'));
        $this->assertSame('Rami', $ticket->guest_name);
    }

    public function test_the_ticket_page_follows_the_ticket_with_the_cookie_only(): void
    {
        $joined = $this->submitJoin();
        $ticket = QueueTicket::query()->sole();
        $token = $joined->getCookie(GuestTicketCookies::name($ticket->public_id))->getValue();
        $this->ticketIn($this->session, TicketStatus::Serving, ['served_by' => $this->owner->getKey()]);

        $page = $this->withCookie(GuestTicketCookies::name($ticket->public_id), $token)->get('/ticket/'.$ticket->public_id);

        $page->assertOk()
            ->assertSee('HC-001')
            ->assertSee('You are in the queue.')
            ->assertSee('Nobody ahead of you')
            ->assertSee('HC-002')
            ->assertSee('<meta http-equiv="refresh" content="20">', false)
            ->assertSee('Leave the queue');
    }

    public function test_the_ticket_page_needs_the_cookie(): void
    {
        $this->submitJoin();
        $ticket = QueueTicket::query()->sole();

        $this->get('/ticket/'.$ticket->public_id)->assertNotFound()->assertSee('Ticket not found');
        $this->withCookie(GuestTicketCookies::name($ticket->public_id), str_repeat('a', 48))
            ->get('/ticket/'.$ticket->public_id)
            ->assertNotFound();
    }

    public function test_invalid_details_re_render_the_form_with_messages(): void
    {
        $this->queue->update(['require_guest_phone' => true]);

        $missing = $this->submitJoin(['name' => '']);
        $noConsent = $this->submitJoin(['name' => 'Rami', 'phone' => '+963944123456']);

        $missing->assertStatus(422)->assertSee('Enter your name')->assertSee('Enter your phone number');
        $noConsent->assertStatus(422)->assertSee('Please agree')->assertSee('value="+963944123456"', false);
        $this->assertSame(0, QueueTicket::query()->count());
    }

    public function test_joining_a_queue_that_closed_meanwhile_explains_why(): void
    {
        $nonce = $this->formNonce();
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();
        $this->forgetAuthenticatedUser();

        $response = $this->withCookie(GuestTicketCookies::FORM_COOKIE, $nonce)
            ->post($this->joinUri(), ['name' => 'Rami', '_form' => $nonce]);

        $response->assertStatus(409)->assertSee('The queue is closed right now.');
    }

    public function test_a_browser_holding_a_ticket_is_sent_back_to_it(): void
    {
        $joined = $this->submitJoin();
        $ticket = QueueTicket::query()->sole();
        $cookie = GuestTicketCookies::name($ticket->public_id);
        $token = $joined->getCookie($cookie)->getValue();

        $this->withCookie($cookie, $token)->get($this->joinUri())->assertSee('You already have ticket HC-001');
        $again = $this->withCookie($cookie, $token)->submitJoin();

        $again->assertStatus(303)->assertRedirect('/ticket/'.$ticket->public_id);
        $this->assertSame(1, QueueTicket::query()->count());
    }

    // ----- Cross-site protection ----------------------------------------

    public function test_forms_reject_cross_site_and_nonce_less_submissions(): void
    {
        $nonce = $this->formNonce();

        $noNonce = $this->post($this->joinUri(), ['name' => 'Rami']);
        $wrongNonce = $this->withCookie(GuestTicketCookies::FORM_COOKIE, $nonce)
            ->post($this->joinUri(), ['name' => 'Rami', '_form' => str_repeat('b', 40)]);
        $crossSite = $this->withCookie(GuestTicketCookies::FORM_COOKIE, $nonce)
            ->withHeaders(['Sec-Fetch-Site' => 'cross-site'])
            ->post($this->joinUri(), ['name' => 'Rami', '_form' => $nonce]);
        $foreignOrigin = $this->withCookie(GuestTicketCookies::FORM_COOKIE, $nonce)
            ->withHeaders(['Sec-Fetch-Site' => 'same-origin', 'Origin' => 'https://evil.example'])
            ->post($this->joinUri(), ['name' => 'Rami', '_form' => $nonce]);

        foreach ([$noNonce, $wrongNonce, $crossSite, $foreignOrigin] as $response) {
            $response->assertStatus(419)->assertSee('This page has expired');
        }
        $this->assertSame(0, QueueTicket::query()->count());
    }

    /**
     * Regression: with Referrer-Policy no-referrer, browsers post forms with
     * "Origin: null"; Sec-Fetch-Site decides.
     */
    public function test_a_real_browser_form_post_with_a_null_origin_is_accepted(): void
    {
        $nonce = $this->formNonce();

        $browser = $this->withCookie(GuestTicketCookies::FORM_COOKIE, $nonce)
            ->withHeaders(['Sec-Fetch-Site' => 'same-origin', 'Origin' => 'null'])
            ->post($this->joinUri(), ['name' => 'Rami', '_form' => $nonce]);
        $crossSite = $this->withCookie(GuestTicketCookies::FORM_COOKIE, $nonce)
            ->withHeaders(['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'null'])
            ->post($this->joinUri(), ['name' => 'Rami', '_form' => $nonce]);

        $browser->assertStatus(303);
        $crossSite->assertStatus(419);
        $this->assertSame(1, QueueTicket::query()->count());
    }

    /**
     * The test suite skips Laravel's session-based forgery checks, so this
     * guards that the guest forms (which keep no session) never rely on them.
     */
    public function test_guest_forms_do_not_depend_on_session_csrf(): void
    {
        foreach (['guest.join.store', 'guest.ticket.cancel'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertContains(PreventRequestForgery::class, $route->excludedMiddleware(), $name);
            $this->assertContains(ValidateCsrfToken::class, $route->excludedMiddleware(), $name);
            $this->assertContains('guest.form', $route->gatherMiddleware(), $name);
        }
    }

    // ----- Cancelling ---------------------------------------------------

    public function test_the_guest_leaves_the_queue_from_the_ticket_page(): void
    {
        $joined = $this->submitJoin();
        $ticket = QueueTicket::query()->sole();
        $cookie = GuestTicketCookies::name($ticket->public_id);
        $token = $joined->getCookie($cookie)->getValue();

        $withoutCookie = $this->withHeaders(['Sec-Fetch-Site' => 'same-origin'])->post('/ticket/'.$ticket->public_id.'/cancel');
        $crossSite = $this->withCookie($cookie, $token)
            ->withHeaders(['Sec-Fetch-Site' => 'cross-site'])
            ->post('/ticket/'.$ticket->public_id.'/cancel');
        $this->assertSame(TicketStatus::Waiting, $ticket->fresh()->status);

        $cancelled = $this->withCookie($cookie, $token)
            ->withHeaders(['Sec-Fetch-Site' => 'same-origin'])
            ->post('/ticket/'.$ticket->public_id.'/cancel');

        $withoutCookie->assertNotFound();
        $crossSite->assertStatus(419);
        $cancelled->assertStatus(303)->assertRedirect('/ticket/'.$ticket->public_id);
        $this->assertSame(TicketStatus::Cancelled, $ticket->fresh()->status);
        $this->withCookie($cookie, $token)
            ->get('/ticket/'.$ticket->public_id)
            ->assertOk()
            ->assertSee('This ticket was cancelled.')
            ->assertDontSee('http-equiv="refresh"', false)
            ->assertDontSee('Leave the queue');
    }
}
