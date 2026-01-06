<?php

namespace App\EventListener;

use Symfony\Component\Workflow\Event\TransitionEvent;
use Psr\Log\LoggerInterface;
use Pimcore\Model\Element\Note;

class ProductWorkflowListener
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onWorkflowTransition(TransitionEvent $event): void
    {
        $object = $event->getSubject();

        if ($object instanceof \Pimcore\Model\DataObject\Product) {
            $transition = $event->getTransition();
            $froms = implode(',', $transition->getFroms());
            $to = $transition->getTos()[0];

            $this->logger->info(sprintf(
                "Workflow Transition on Product [ID: %s]: %s → %s",
                $object->getId(),
                $froms,
                $to
            ));
        }

        // Create Pimcore Note to see the history
        $note = new Note();
        $note->setElement($object);
        $note->setDate(time());
        $note->setType('workflow');
        $note->setTitle('Workflow Transition');
        $note->setDescription(sprintf(
            'Product moved from "%s" to "%s" via transition "%s"',
            $froms,
            $to,
            $transition->getName()
        ));
        $note->save();
    }
}
