<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App;

use App\Filament\App\Pages\Checkout;
use App\Filament\App\Pages\PurchaseTickets;
use App\Models\Ticketing\Cart;
use App\Models\Ticketing\TicketType;
use App\Models\User;
use App\Services\CartService;
use App\Services\StripeService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CheckoutPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    #[Test]
    public function checkout_stripe_mount_is_wire_ignored_alongside_cart_poll(): void
    {
        $view = file_get_contents(resource_path('views/filament/app/pages/checkout.blade.php'));

        $this->assertNotFalse($view);
        $this->assertStringContainsString('wire:poll.10s="assertCheckoutCartIsCurrent"', $view);
        $this->assertMatchesRegularExpression(
            '/id="stripe-checkout"\s+wire:ignore/',
            $view,
            'Stripe embedded checkout must use wire:ignore so Livewire poll morphs do not clear the mount node.',
        );
    }

    #[Test]
    public function assert_checkout_cart_is_current_leaves_checkout_open_when_cart_matches(): void
    {
        $user = User::firstOrFail();
        $session = $this->openCheckoutSession();

        $this->mockStripeCheckoutSession($session);
        $this->createActiveCheckoutCart($user, $session->id);

        Livewire::actingAs($user)
            ->test(Checkout::class)
            ->assertSet('checkoutComplete', false)
            ->assertSeeHtml('id="stripe-checkout" wire:ignore')
            ->call('assertCheckoutCartIsCurrent')
            ->assertSet('checkoutComplete', false)
            ->assertSeeHtml('id="stripe-checkout" wire:ignore');
    }

    #[Test]
    public function assert_checkout_cart_is_current_redirects_when_stripe_session_changes(): void
    {
        $user = User::firstOrFail();
        $session = $this->openCheckoutSession();

        $this->mockStripeCheckoutSession($session);
        $cart = $this->createActiveCheckoutCart($user, $session->id);

        $component = Livewire::actingAs($user)->test(Checkout::class);

        $cart->setStripeCheckoutIdAndSave('cs_test_replaced_session');

        $component
            ->call('assertCheckoutCartIsCurrent')
            ->assertRedirect(PurchaseTickets::getUrl());
    }

    protected function openCheckoutSession(string $id = 'cs_test_checkout'): Session
    {
        return Session::constructFrom([
            'id' => $id,
            'object' => 'checkout.session',
            'status' => 'open',
            'client_secret' => 'cs_test_secret',
        ]);
    }

    protected function mockStripeCheckoutSession(Session $session): void
    {
        $this->instance(StripeService::class, Mockery::mock(StripeService::class, function (MockInterface $mock) use ($session) {
            $mock->shouldReceive('getCheckoutSession')->andReturn($session);
        }));
    }

    protected function createActiveCheckoutCart(User $user, string $sessionId): Cart
    {
        Auth::login($user);

        $ticketType = TicketType::query()->available()->firstOrFail();

        /** @var CartService $cartService */
        $cartService = app(CartService::class);

        $cart = $cartService->createCartAndItems([
            ['id' => $ticketType->id, 'quantity' => 1],
        ], []);

        $cart->setStripeCheckoutIdAndSave($sessionId);

        return $cart;
    }
}
