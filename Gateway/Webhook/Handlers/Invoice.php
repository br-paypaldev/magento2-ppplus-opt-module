<?php

namespace Paypal\PaypalPlusBrasil\Gateway\Webhook\Handlers;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice as MagentoInvoice;
use Magento\Sales\Model\Service\InvoiceService;
use Paypal\PaypalPlusBrasil\Logger\Logger;

class Invoice extends AbstractHandler
{
    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @param Logger $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param Transaction $transaction
     */
    public function __construct(
        Logger $logger,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Transaction $transaction
    ) {
        parent::__construct($logger, $orderRepository, $searchCriteriaBuilder);
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transaction = $transaction;
    }

    /**
     * @param string|Order $order
     *
     * @throws LocalizedException
     */
    public function handle($order)
    {
        try {
            if (is_string($order)) {
                $order = $this->getOrder($order);
            }

            //tratamento para atualizar pedido quando o mesmo estiver em pending
            $order->setState(Order::STATE_PROCESSING);

            if (!$order->canInvoice()) {
                throw new LocalizedException(__('Cannot create invoice for order %1!', $order->getIncrementId()));
            }

            /** @var \Magento\Sales\Model\Order\Invoice $invoice */
            $invoice = $this->invoiceService->prepareInvoice($order);
            if (!$invoice) {
                throw new LocalizedException(__("The invoice can't be saved at this time. Please try again later."));
            }

            if (!$invoice->getTotalQty()) {
                throw new LocalizedException(
                    __("The invoice can't be created without products. Add products and try again.")
                );
            }
            $invoice->setRequestedCaptureCase(MagentoInvoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->getOrder()->setIsInProcess(true);
            $transation = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
            $transation->save();

            $this->invoiceSender->send($invoice);
        } catch (\Exception $exception) {
            $this->logger->error(__('Webhook: Error on creating order invoice!'));
            $this->logger->error($exception->getMessage());

            throw $exception;
        }
    }

}
