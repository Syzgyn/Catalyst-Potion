<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\Ticketing\Cart;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\StripeService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class Checkout extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.app.pages.checkout';

    public ?string $checkoutSecret;

    public ?string $checkoutId;

    public ?Cart $cart;

    public bool $checkoutComplete = false;

    public ?string $checkoutContent;

    public ?string $checkoutCompleteContent;

    public ?int $mountedCartId = null;

    public function mount(CartService $cartService, StripeService $stripeService): void
    {
        $this->cart = $cartService->getActiveCart();

        if (! $this->cart) {
            $this->redirect(PurchaseTickets::getUrl());

            return;
        }

        $this->mountedCartId = $this->cart->id;

        if (! $this->cart->stripe_checkout_id) {
            $this->cart->expire();
            $this->redirect(PurchaseTickets::getUrl());

            Log::warning('Cart is missing Stripe Checkout ID.', [
                'cart_id' => $this->cart->id,
                'user_id' => $this->cart->user_id,
            ]);

            Notification::make()
                ->title('Cart Error')
                ->body('There was an error with your cart. Please try again.')
                ->danger()
                ->send();

            return;
        }

        $session = $stripeService->getCheckoutSession($this->cart->stripe_checkout_id);

        if ($session->status !== 'open') {
            $this->redirect(PurchaseTickets::getUrl());
        }

        $this->checkoutId = $this->cart->stripe_checkout_id;
        $this->checkoutSecret = $session->client_secret;

        $this->checkoutContent = $this->cart->event->checkoutContent->formattedContent ?? null;
        $this->checkoutCompleteContent = $this->cart->event->checkoutCompleteContent->formattedContent ?? null;
    }

    public function assertCheckoutCartIsCurrent(CartService $cartService): void
    {
        if ($this->checkoutComplete) {
            return;
        }

        $active = $cartService->getActiveCart();

        if (
            ! $active
            || $active->id !== $this->mountedCartId
            || $active->stripe_checkout_id !== $this->checkoutId
        ) {
            $this->redirectToPurchaseWithSupersededCartNotice();
        }
    }

    public function completeCheckout(
        string $sessionId,
        CartService $cartService,
        StripeService $stripeService,
        CheckoutService $checkoutService,
    ): void {
        $active = $cartService->getActiveCart();

        if (! $active || $active->stripe_checkout_id !== $sessionId) {
            $this->redirectToPurchaseWithSupersededCartNotice();

            return;
        }

        $this->checkoutComplete = true;
        $session = $stripeService->getCheckoutSession($sessionId);
        $checkoutService->resolveCompletedCheckoutSession($session);
    }

    protected function redirectToPurchaseWithSupersededCartNotice(): void
    {
        Notification::make()
            ->title('Cart updated')
            ->body('Your cart was updated elsewhere. Please review your order and checkout again.')
            ->warning()
            ->send();

        $this->redirect(PurchaseTickets::getUrl());
    }

    public function cancelAction(): Action
    {
        return Action::make('cancel')
            ->extraAttributes(['class' => 'mb-2 ms-6'])
            ->label('Cancel Order')
            ->action(function (CartService $cartService) {
                $cartService->getActiveCart()?->delete();
                $this->redirect(PurchaseTickets::getUrl());
            })
            ->color('danger')
            ->icon('heroicon-o-x-mark');
    }
}
