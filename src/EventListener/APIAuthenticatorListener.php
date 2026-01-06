<?php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Pimcore\Model\DataObject\ApiToken;

class APIAuthenticatorListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['checkToken'],
        ];
    }

    public function checkToken(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Apply for all routes which has /api
        if (strpos($request->getRequestUri(), 'api')!==false ) {
            $apiKey = $request->headers->get('apikey');

            if (!$this->isValidToken($apiKey)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Unauthorized. Invalid API key.'
                ], 401));
            }
        }
    }

    private function isValidToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $tokens = new ApiToken\Listing();
        $tokens->setCondition('token = ?', [$token]);
        $tokens->setUnpublished(false);

        return ($tokens->getCount()) > 0;
    }
}
