<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\CoreBundle\EventListener;

use Doctrine\Common\Persistence\ObjectManager;
use Sylius\Bundle\UserBundle\Event\UserEvent;
use Sylius\Component\Cart\Context\CartContextInterface;
use Sylius\Component\Cart\Context\CartNotFoundException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * @author Michał Marcinkowski <michal.marcinkowski@lakion.com>
 */
final class CartBlamerListener
{
    /**
     * @var ObjectManager
     */
    private $cartManager;

    /**
     * @var CartContextInterface
     */
    private $cartContext;

    /**
     * @param ObjectManager $cartManager
     * @param CartContextInterface $cartContext
     */
    public function __construct(ObjectManager $cartManager, CartContextInterface $cartContext)
    {
        $this->cartManager = $cartManager;
        $this->cartContext = $cartContext;
    }

    /**
     * @param UserEvent $userEvent
     */
    public function onImplicitLogin(UserEvent $userEvent)
    {
        $this->blame($userEvent->getUser());
    }

    /**
     * @param InteractiveLoginEvent $interactiveLoginEvent
     */
    public function onInteractiveLogin(InteractiveLoginEvent $interactiveLoginEvent)
    {
        $user = $interactiveLoginEvent->getAuthenticationToken()->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $this->blame($user);
    }

    /**
     * @param UserInterface $user
     */
    private function blame(UserInterface $user)
    {
        $cart = $this->getCart();
        if (null === $cart) {
            return;
        }

        $cart->setCustomer($user->getCustomer());
        $this->cartManager->persist($cart);
        $this->cartManager->flush();
    }

    /**
     * @return OrderInterface
     *
     * @throws UnexpectedTypeException
     */
    private function getCart()
    {
        try {
            $cart = $this->cartContext->getCart();
        } catch (CartNotFoundException $exception) {
            return null;
        }

        if (!$cart instanceof OrderInterface) {
            throw new UnexpectedTypeException($cart, OrderInterface::class);
        }

        return $cart;
    }
}
