<?php

namespace PHPCuong\OutOfStockItem\Observer;

class RemoveOutOfStockItem implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\CatalogInventory\Model\Stock\StockItemRepository
     */
    protected $stockItemRepository;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->stockItemRepository = $stockItemRepository;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote $quoteInfo */
        $quoteInfo = $this->checkoutSession->getQuote();
        $cartId = $quoteInfo->getId();
        // Get all visible items in the cart
        $itemsInCart = $quoteInfo->getAllVisibleItems();
        $removals = false;
        // Loop all items in the cart and check that item, if this item is out of stock then remove it from the cart.
        foreach ($itemsInCart as $item) {
            try {
                $stockItem = $this->getStockItem($item->getProductId());
                // If this item is out of stock then remove it
                if (!$stockItem->getIsInStock()) {
                    $item->delete();
                    $removals = true;
                }
            } catch(\Exception $e) {}
        }
        // Collect total quote after removing items
        // We must update the total quote, example: qty, price
        if ($removals) {
            try {
                /** @var \Magento\Quote\Model\Quote $quote */
                $quote = $this->quoteRepository->getActive($cartId);
                $quote->getBillingAddress();
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quote->collectTotals();
                $this->quoteRepository->save($quote);
            } catch(\Exception $e) {}
        }
    }

    /**
     * @param int $productId
     * @return \Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory
     */
    private function getStockItem($productId)
    {
        return $this->stockItemRepository->get($productId);
    }
}
