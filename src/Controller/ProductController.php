<?php

namespace App\Controller;

use Carbon\Carbon;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ClassificationStoreHelper;


class ProductController extends FrontendController
{
    private ClassificationStoreHelper $csHelper;

    public function __construct(ClassificationStoreHelper $csHelper)
    {
        $this->csHelper = $csHelper;
    }
    /**
     * CREATE PRODUCT
     */
    #[Route('/api/create-product', name: 'product_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['productName'])) {
            return new JsonResponse(['error' => 'Missing product name field'], 400);
        }

       try {
        // --- 1. Get the next sequential SKU ---
        $listing = new Product\Listing();
        $listing->setOrderKey('sku');
        $listing->setOrder('DESC');
        $listing->setLimit(1);
        $latestProduct = $listing->current();

        $lastSkuNumber = 0;
        if ($latestProduct && preg_match('/SKU-(\d+)/', $latestProduct->getSku(), $matches)) {
            $lastSkuNumber = (int)$matches[1];
        }
        $nextSkuNumber = $lastSkuNumber + 1;
        $sku = 'SKU-' . str_pad($nextSkuNumber, 3, '0', STR_PAD_LEFT);

        // --- 2. Create product folder ---
        $folderPath = '/Product/' . $sku;
        $parentFolder = \Pimcore\Model\DataObject\Service::createFolderByPath($folderPath);

        // --- 3. Create Product object ---
        $product = new Product();
        $product->setParent($parentFolder);
        $product->setKey(\Pimcore\Model\DataObject\Service::getValidKey($data['productName'], 'object'));
        $product->setProductName($data['productName']);
        $product->setSku($sku);
        $product->setPrice($data['price']);
        $product->setStockQuantity($data['stockQuantity'] ?? 0);
        $product->setDescription($data['description'] ?? '');
        $product->setPriceUpdatedAt(null);

        //-- Set productImage if the image is provided
        if (!empty($data['productImage'])) {
            $asset = Asset::getById($data['productImage']);
            if ($asset instanceof Asset\Image) {
                $product->setProductImage($asset);
            }
        }

        // Classificationstore handling
        $cs = $this->csHelper->mapDataToClassificationStore($data, $product->getAttributes());
        $product->setAttributes($cs);

        // --- Set nextAvailableOn logic ---
        if (($data['stockQuantity'] ?? 0) <= 0) {
            // Set the Next Availabe to 6 days later wif the stock is 0
                $product->setNextAvailableOn((Carbon::now())->modify('+6 days'));

        } else {
            $product->setNextAvailableOn(null); // stock > 0
        }

        // Optional Category assignment
        if (!empty($data['categoryId'])) {
            $category = \Pimcore\Model\DataObject\Category::getById($data['categoryId']);
            if ($category instanceof \Pimcore\Model\DataObject\Category) {
                $product->setCategory($category);
            }
        }

        $product->save();

        return new JsonResponse([
            'success' => true,
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'path' => $folderPath
        ]);

    } catch (\Exception $e) {
        return new JsonResponse(['error' => $e->getMessage()], 500);
    }
    }

     /**
     * GET PRODUCT BY ID
     */
    #[Route('/api/product-by-id/{id}', name: 'api_product_get', methods: ['GET'])]
    public function getProduct(int $id): JsonResponse
    {
        // if ($auth = $this->checkAuth($request)) return $auth;

        $product = Product::getById($id);
        if (!$product instanceof Product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        return new JsonResponse([
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getProductName(),
            'price' => $product->getPrice(),
            'stockQuantity' => $product->getStockQuantity(),
            'description' => $product->getDescription(),
            'category' => $product->getCategory(),
            // 'images' => array_map(fn($asset) => $asset->getFullPath(), $product->getProductImages() ?? []),
            'whenAvailable' => $product->getNextAvailableOn(),
            'priceUpdatedAt' => $product->getPriceUpdatedAt()
        ]);
    }

    /**
     * UPDATE PRODUCT
     */
    #[Route('/api/update-product/{id}', name: 'api_product_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        // if ($auth = $this->checkAuth($request)) return $auth;

        $product = Product::getById($id);
        if (!$product instanceof Product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['productName'])) $product->setProductName($data['productName']);
        if (isset($data['price'])) $product->setPrice($data['price']);
        if (isset($data['stockQuantity'])) $product->setStockQuantity($data['stockQuantity']);
        if (isset($data['description'])) $product->setDescription($data['description']);

        if (isset($data['categoryId'])) {
            $category = Category::getById($data['categoryId']);
            if ($category instanceof Category) {
                $product->setCategory($category);
            }
        }

        $product->save();

        return new JsonResponse(['success' => true, 'message' => 'Product updated']);
    }

    /**
     * DELETE PRODUCT
     */
    #[Route('/api/delete-product/{id}', name: 'api_product_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        // if ($auth = $this->checkAuth($request)) return $auth;

        $product = Product::getById($id);
        if (!$product instanceof Product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $product->delete();
        return new JsonResponse(['success' => true, 'message' => 'Product deleted']);
    }

    /**
     * LIST + FILTER PRODUCTS
     */
    #[Route('/api/filter-product', name: 'api_product_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // if ($auth = $this->checkAuth($request)) return $auth;

        $categoryId = $request->query->get('categoryId');
        $minPrice   = $request->query->get('minPrice');
        $maxPrice   = $request->query->get('maxPrice');

        $listing = new Product\Listing();

        if ($categoryId) {
            $listing->addConditionParam('category__id = ?', $categoryId);
        }
        if ($minPrice) {
            $listing->addConditionParam('price >= ?', (float)$minPrice);
        }
        if ($maxPrice) {
            $listing->addConditionParam('price <= ?', (float)$maxPrice);
        }

        $result = [];
        foreach ($listing as $product) {
            $result[] = [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getProductName(),
                'price' => $product->getPrice(),
                'stockQuantity' => $product->getStockQuantity(),
            ];
        }

        return new JsonResponse($result);
    }
}
