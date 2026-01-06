<?php

namespace App\Command;

use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\ClassificationStoreHelper;


class ImportProductsCommand extends Command
{
    protected static $defaultName = 'app:import-products';

    private ClassificationStoreHelper $csHelper;

    // inject ClassificationStoreHelper via constructor
    public function __construct(ClassificationStoreHelper $csHelper) {
        parent::__construct();

        $this->csHelper = $csHelper;
    }


    protected function configure()
    {
        $this
            ->setDescription('Import products from a CSV file into Pimcore')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to CSV file');
    }



    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error("CSV file not found: $filePath");
            return Command::FAILURE;
        }

        if (($handle = fopen($filePath, 'r')) === false) {
            $io->error("Unable to open CSV file.");
            return Command::FAILURE;
        }

        $header = fgetcsv($handle, 0, ',');
        $rowCount = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rowCount++;
            $data = array_combine($header, $row);

            // Create product
            $product = new Product();
            $product->setKey(uniqid('product_'));
            $product->setParentId(27);
            $product->setPublished(true);

            $product->setProductName($data['productName'] ?? null);
            $product->setDescription($data['description'] ?? null);
            $product->setPrice((float) ($data['price'] ?? 0));
            $product->setStockQuantity((int) ($data['stockQuantity'] ?? 0));  #nextAvailableOn will be set automatically if stock is zero by event listener

            // Handle Category relation
            if (!empty($data['category'])) {
                $category = Category::getByPath('/' . $data['category']);
                if ($category instanceof Category) {
                    $product->setCategory($category);
                }
            }

            // Handle Classification Store attributes
            // $cs = $product->getAttributes();

            // $storeConfig = StoreConfig::getById('ProductAttributes');

            // // Get key definitions
            // $keyDefs = $storeConfig->getKeys();

            // foreach ($data as $key => $value) {
            //     if (in_array($key, ['productName','category','description','price','stockQuantity','whenAvailable'])) {
            //         continue;
            //     }

            //     if (!empty($value)) {
            //         $groupKey = null;

            //         foreach ($keyDefs as $defKey) {
            //             if ($defKey->getName() === $key) {
            //                 $groupId = $defKey->getGroupId();
            //                 $group = $storeConfig->getGroup($groupId);
            //                 $groupKey = $group->getName();
            //                 break;
            //             }
            //         }

            //         if ($groupKey) {
            //             $cs->setLocalizedKeyValue($groupKey, $key, $value, "en");
            //         } else {
            //             $io->warning("Unknown attribute key '$key' in CSV – skipped.");
            //         }
            //     }
            // }
            // $product->setAttributes($cs);
            $cs = $this->csHelper->mapDataToClassificationStore($data, $product->getAttributes(), $io);
            $product->setAttributes($cs);

            $product->save();
            $io->success("Imported product: " . $product->getProductName());
        }

        fclose($handle);

        $io->success("Imported $rowCount products successfully.");
        return Command::SUCCESS;
    }
}
