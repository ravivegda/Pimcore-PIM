<?php

namespace App\Service;

use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Pimcore\Model\DataObject\Classificationstore;
use Symfony\Component\Console\Style\SymfonyStyle;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig\Listing as KeyConfigListing;

class ClassificationStoreHelper
{
    private int $storeId;

    public function __construct(int $storeId = 1) // Default store ID = 1
    {
        $this->storeId = $storeId;
    }

    /**
     * Map CSV/API data into a classification store object
     */
    public function mapDataToClassificationStore(array $data, ?Classificationstore $cs = null, ?SymfonyStyle $io = null): Classificationstore
    {
        if (!$cs) {
            $cs = new Classificationstore();
        }

        $storeConfig = StoreConfig::getById($this->storeId);

        // ✅ use Listing to fetch keys
        $keyListing = new KeyConfigListing();
        $keyListing->setCondition("storeId = ?", [$storeConfig->getId()]);
        $keyDefs = $keyListing->load();

        foreach ($data as $key => $value) {
            if (in_array($key, ['productName','category','description','price','stockQuantity','nextAvailableOn','productImage', 'productId'])) {
                continue; // skip normal fields
            }

            if (!empty($value)) {
                $groupKey = null;

                foreach ($keyDefs as $defKey) {
                    if ($defKey->getName() === $key) {
                        $groupId = $defKey->getGroupId();
                        $group = $storeConfig->getGroup($groupId);
                        $groupKey = $group->getName();
                        break;
                    }
                }

                if ($groupKey) {
                    $cs->setLocalizedKeyValue($groupKey, $key, $value, "en");
                } else {
                    if ($io) {
                        $io->warning("Unknown attribute key '$key' – skipped.");
                    }
                }
            }
        }

        return $cs;
    }
}
