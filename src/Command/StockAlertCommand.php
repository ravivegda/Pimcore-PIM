<?php

namespace App\Command;

use Pimcore\Model\DataObject\Product\Listing;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:stock-alert',
    description: 'Send email if stock quantity is less than 5'
)]
class StockAlertCommand extends Command
{
    private MailerInterface $mailer;
    private string $alertEmail;

    public function __construct(MailerInterface $mailer, string $alertEmail = 'rehana.zenab@g10x.com')
    {
        parent::__construct();
        $this->mailer = $mailer;
        $this->alertEmail = $alertEmail; // configurable via services.yaml or .env
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lowStockProducts = (new Listing())->load();

        $body = "Products with low stock:\n\n";
        foreach ($lowStockProducts as $product) {
            if ($product->getStockQuantity() < 5) {
                $body .= sprintf(
                    "SKU: %s | Name: %s | Stock: %d\n",
                    $product->getSku(),
                    $product->getProductName(),
                    $product->getStockQuantity()
                );
            }
        }

        if (trim($body) !== "Products with low stock:") {
            $email = (new \Pimcore\Mail())
                ->from('rehanazainab91@gmail.com')
                ->to($this->alertEmail)
                ->subject('Low Stock Alert')
                ->text($body);

            $$email->send();
        }

        $output->writeln("Stock alert email sent.");
        return Command::SUCCESS;
    }
}
