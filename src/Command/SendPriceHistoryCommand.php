<?php
// src/Command/SendPriceHistoryCommand.php
namespace App\Command;

use Pimcore\Model\DataObject\Product;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SendPriceHistoryCommand extends Command
{
    protected static $defaultName = 'app:send-price-history';

    private $mailer;
    private $params;

    public function __construct(MailerInterface $mailer, ParameterBagInterface $params)
    {
        parent::__construct();
        $this->mailer = $mailer;
        $this->params = $params;
    }

    protected function configure()
    {
        $this
            ->setDescription('Send product price history via email')
            ->addArgument('productId', InputArgument::REQUIRED, 'The ID of the product');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $productId = $input->getArgument('productId');

        /** @var Product|null $product */
        $product = Product::getById($productId);

        if (!$product instanceof Product) {
            $output->writeln("<error>Product not found!</error>");
            return Command::FAILURE;
        }

        $sku = $product->getSku();
        $productName = $product->getProductName();


        $priceHistoryList = new Product\Listing();
        $priceHistoryList->setCondition('productId = ?', [$productId]);
        $priceHistoryList->getPriceUpdatedAt('priceUpdatedAt');
        $priceHistoryList->setOrder('DESC');

        $historyData = [];
        foreach ($priceHistoryList as $history) {
            /** @var PriceHistory $history */
            $historyData[] = sprintf(
                "Date: %s | Price: %s",
                $history->getPriceUpdatedAt()->format('Y-m-d H:i:s'),
                $history->getPrice()
            );
        }

        if (empty($historyData)) {
            $output->writeln("<comment>No price history found for product.</comment>");
            return Command::SUCCESS;
        }

        $emailBody = sprintf(
            "Price History for Product\n\nSKU: %s\nName: %s\n\n%s",
            $sku,
            $productName,
            implode("\n", $historyData)
        );

        $toEmail = $this->params->get('price_history_email_to');

        $email = (new Email())
            ->from($this->params->get('mailer_from')) // set in .env or services.yaml
            ->to($toEmail)
            ->subject("Price History for $productName (SKU: $sku)")
            ->text($emailBody);

        $this->mailer->send($email);

        $output->writeln("<info>Price history email sent successfully to $toEmail.</info>");

        return Command::SUCCESS;
    }
}
