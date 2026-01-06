<?php

namespace App\Command;

use Carbon\Carbon;
use Pimcore\Model\DataObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:price-fetch',
    description: 'Fetch product prices from API and update in Pimcore'
)]
class PriceFetchCommand extends Command
{
    // private HttpClientInterface $client;
    private LoggerInterface $logger;
    private int $expiryHours;

    public function __construct(LoggerInterface $logger, int $expiryHours = 6)
    {
        parent::__construct();
        // $this->client = $client;
        $this->logger = $logger;
        $this->expiryHours = $expiryHours; // configurable
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $products = new DataObject\Product\Listing();

        foreach ($products as $product) {
            $price = $product->getPrice();
            $lastUpdated = $product->getPriceUpdatedAt();

            $needsUpdate = false;

            if (empty($price) || $price == 0) {
                $needsUpdate = true;
            } elseif ($lastUpdated instanceof \Carbon\Carbon) {
                if ($lastUpdated->diffInHours(Carbon::now()) >= $this->expiryHours) {
                    $needsUpdate = true;
                }
            } elseif ($lastUpdated instanceof \DateTimeInterface) {
                $interval = (new \DateTime())->diff($lastUpdated);
                if ($interval->h + ($interval->days * 24) >= $this->expiryHours) {
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                $url = "http://www.randomnumberapi.com/api/v1.0/random?min=10&max=999&count=1&prodcut_id=" . $product->getId();

                try {
                    // $response = $this->client->request('GET', $url);
                    $response = file_get_contents($url);
                    // $data = $response->toArray();
                    $data = json_decode($response, true);

                    if (!empty($data[0])) {
                        $newPrice = $data[0];
                        $product->setPrice($newPrice);
                        $product->setPriceUpdatedAt(Carbon::now());
                        $product->save();

                        $this->logger->info("Updated price for product {$product->getId()} | URL: $url | Response: " . json_encode($data));
                    }
                } catch (\Throwable $e) {
                    $this->logger->error("Failed fetching price for product {$product->getId()} | Error: " . $e->getMessage());
                }
            }
        }

        $output->writeln("Price fetch completed.");
        return Command::SUCCESS;
    }
}
