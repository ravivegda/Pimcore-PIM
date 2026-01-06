<?php

namespace App\EventListener;

use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Folder;
use Carbon\Carbon;
use DateTime;

class ProductEventListener
{
    /**
     * Auto-generate SKU and validate stock availability date
     */
    public function onPreAdd(DataObjectEvent $event): void
    {
        $object = $event->getObject();

        if (!$object instanceof Product) {
            return;
        }

        // ✅ Auto-generate SKU if empty
        if (empty($object->getSku())) {
            $skuNumber = $this->generateNextSku();
            $object->setSku($skuNumber);
        }

         // ✅ Ensure folder exists: /Product/<SKU>
        $skuFolder = $this->getOrCreateFolder("/Product/" . $object->getSku());

        // Set parent folder and key
        $object->setParentId($skuFolder->getId());
    }

    public function onPreUpdate(DataObjectEvent $event): void
    {
        $object = $event->getObject();

        if (!$object instanceof Product) {
            return;
        }
    }

     public function onPostLoad(DataObjectEvent $event): void
    {
        $object = $event->getObject();

        if ($object instanceof Product) {
            // If price is empty, set "nextAvailableOn"
            if (empty($object->getStockQuantity()) || $object->getStockQuantity() == 0) {
                $object->setNextAvailableOn((Carbon::now())->modify('+6 days'));
            }

            // If stock exists, clear "nextAvailableOn"
            if (!empty($object->getStockQuantity()) && $object->getStockQuantity() > 0) {
                $object->setNextAvailableOn(null);
            }
        }
    }

    /**
     * Generate Next SKU in format: SKU-001, SKU-002 ...
     */
    private function generateNextSku(): string
    {
        // Get last created Product object
        $list = new Product\Listing();
        $list->setOrderKey('sku');
        $list->setOrder('DESC');
        $list->setLimit(1);

        $lastSku = null;
        foreach ($list as $item) {
            $lastSku = $item->getSku();
        }

        $nextNumber = 1;
        if ($lastSku && preg_match('/SKU-(\d+)/', $lastSku, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        }

        return sprintf("SKU-%03d", $nextNumber);
    }

     private function getOrCreateFolder(string $path): Folder
    {
        $folder = Folder::getByPath($path);
        if (!$folder) {
            $folder = Folder::create([
                'key' => basename($path),
                'parentId' => Folder::getByPath(dirname($path))->getId(),
            ]);
        }
        return $folder;
    }
}
