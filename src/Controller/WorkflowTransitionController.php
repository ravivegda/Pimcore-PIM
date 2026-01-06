<?php

namespace App\Controller;

use Pimcore\Model\DataObject\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\Registry;


class WorkflowTransitionController extends AbstractController
{
    private Registry $workflowRegistry;

    public function __construct(Registry $workflowRegistry)
    {
        $this->workflowRegistry = $workflowRegistry;

    }

   
    #[Route('/api/transition', name:'api_product_workflow_transition', methods: ['POST'])]

    public function transition(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $productId = $data['productId'] ?? null;
        $transitionName = $data['transition'] ?? null;

        if (!$productId || !$transitionName) {
            return $this->json(['error' => 'Missing productId or transition'], 400);
        }

        /** @var Product $product */
        $product = Product::getById($productId);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], 404);
        }

        // 🔒 Role-based restrictions
        if (
        !$this->isGranted('ROLE_PRODUCT_CREATOR') &&
        !$this->isGranted('ROLE_PIMCORE_ADMIN') &&
        $transitionName === 'to_review')
        {
            return $this->json(['error' => 'You do not have permission for this transition'], 403);
        }

        if (!$this->isGranted('ROLE_PIMCORE_ADMIN') && !$this->isGranted('ROLE_PRODUCT_REVIEWER') && in_array($transitionName, ['to_approve', 'reject_from_review'])) {
            return $this->json(['error' => 'You do not have permission for this transition'], 403);
        }

        if (!$this->isGranted('ROLE_PIMCORE_ADMIN') && !$this->isGranted('ROLE_PRODUCT_APPROVER') && $transitionName === 'reject_from_approved') {
            return $this->json(['error' => 'You do not have permission for this transition'], 403);
        }

        // ✅ Get workflow object
        $workflow = $this->workflowRegistry->get($product, 'product_workflow');

        // Check if transition is allowed
        if (!$workflow->can($product, $transitionName)) {
            return $this->json(['error' => 'Transition not allowed for current state'], 403);
        }

        try {
            // Apply transition
            $workflow->apply($product, $transitionName);
            $product->save();


            // Get current state from workflow registry
            $currentPlaces = array_keys($workflow->getMarking($product)->getPlaces());

            return $this->json([
            'success' => true,
            'message' => "Transition '$transitionName' applied successfully",
            'currentState' => $currentPlaces
        ]);


        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/transitions/{id}", methods={"GET"})
     */
    public function availableTransitions(int $id): JsonResponse
    {
        $product = Product::getById($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], 404);
        }

        $workflow = $this->workflowRegistry->get($product, 'product_workflow');
        $transitions = $workflow->getEnabledTransitions($product);

        return $this->json([
            'productId' => $id,
            'currentState' => array_keys($workflow->getMarking($product)->getPlaces()),
            'availableTransitions' => array_map(fn($t) => [
                'name' => $t->getName(),
                'from' => $t->getFroms(),
                'to' => $t->getTos()
            ], $transitions)
        ]);
    }
}
