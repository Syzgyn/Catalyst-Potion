<?php

declare(strict_types=1);

namespace Tests\Unit\Ticketing;

use App\Models\Ticketing\TicketType;
use App\Models\User;
use App\Services\CartService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Checkout\Session;
use Tests\TestCase;

class BeginCheckoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public bool $seed = true;

    #[Test]
    public function begin_checkout_reuses_active_cart_with_same_selection_and_open_session(): void
    {
        $user = User::firstOrFail();
        Auth::login($user);

        $ticketType = TicketType::query()->available()->firstOrFail();
        $tickets = [['id' => $ticketType->id, 'quantity' => 1]];

        $session = Session::constructFrom([
            'id' => 'cs_test_reuse_session',
            'object' => 'checkout.session',
            'status' => 'open',
            'client_secret' => 'cs_test_secret',
        ]);

        $stripeCalls = 0;

        $this->instance(StripeService::class, Mockery::mock(StripeService::class, function (MockInterface $mock) use ($session, &$stripeCalls) {
            /** @var Expectation $create */
            $create = $mock->shouldReceive('createCheckoutFromCart');
            $create->once()->andReturnUsing(function () use ($session, &$stripeCalls) {
                $stripeCalls++;

                return $session;
            });

            /** @var Expectation $getSession */
            $getSession = $mock->shouldReceive('getCheckoutSession');
            $getSession->andReturn($session);

            $mock->shouldReceive('expireCheckoutFromCart');
        }));

        /** @var CartService $cartService */
        $cartService = app(CartService::class);

        $first = $cartService->beginCheckout($tickets);
        $second = $cartService->beginCheckout($tickets);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('cs_test_reuse_session', $second->stripe_checkout_id);
        $this->assertSame(1, $stripeCalls);
    }

    #[Test]
    public function begin_checkout_creates_new_session_when_selection_changes(): void
    {
        $user = User::firstOrFail();
        Auth::login($user);

        $ticketTypes = TicketType::query()->available()->limit(2)->get();
        $this->assertGreaterThanOrEqual(2, $ticketTypes->count());

        $firstType = $ticketTypes->firstOrFail();
        $secondType = $ticketTypes->get(1);

        $sessionIds = ['cs_test_first', 'cs_test_second'];
        $sessionIndex = 0;

        $this->instance(StripeService::class, Mockery::mock(StripeService::class, function (MockInterface $mock) use (&$sessionIndex, $sessionIds) {
            /** @var Expectation $create */
            $create = $mock->shouldReceive('createCheckoutFromCart');
            $create->twice()->andReturnUsing(function () use (&$sessionIndex, $sessionIds) {
                $session = Session::constructFrom([
                    'id' => $sessionIds[$sessionIndex],
                    'object' => 'checkout.session',
                    'status' => 'open',
                    'client_secret' => 'cs_test_secret',
                ]);
                $sessionIndex++;

                return $session;
            });

            /** @var Expectation $getSession */
            $getSession = $mock->shouldReceive('getCheckoutSession');
            $getSession->andReturnUsing(function (string $id) {
                return Session::constructFrom([
                    'id' => $id,
                    'object' => 'checkout.session',
                    'status' => 'open',
                    'client_secret' => 'cs_test_secret',
                ]);
            });

            $mock->shouldReceive('expireCheckoutFromCart');
        }));

        /** @var CartService $cartService */
        $cartService = app(CartService::class);

        $first = $cartService->beginCheckout([['id' => $firstType->id, 'quantity' => 1]]);
        $second = $cartService->beginCheckout([['id' => $secondType->id, 'quantity' => 1]]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('cs_test_second', $second->stripe_checkout_id);
    }
}
