<?php

namespace Lab4u\Sale;

use Bitrix\Sale;
use Lab4u\Sale\Basket\Lab4uItemInterface;


interface BasketRepositoryInterface
{
    /**
     * @return BasketItemCollection
     */
    public function loadAnalysis();

    /**
     * @return BasketItemCollection
     */
    public function loadBiomaterial();

    /**
     * @return BasketItem
     */
    public function loadSubscription();

    public function delete(array $arProductIds);

    /**
     * @param Lab4uItemInterface[] $arItems
     * @return mixed
     */
    public function add(array $arItems);

    public function save(Sale\Basket $basket = null);

    public function clear();

    public function getAnalysisIds(): array;

    public function getBioMaterialIds(): array;

    public function isBasketEmpty(): bool;

    public function clearCoupon();

    public function bioMaterialListIds(int $orderId): array;

    public function bioMaterialListIdsByOrder(Sale\Order $order): array;

    /**
     * @param int $editingOrderId
     */
    public function setEditingOrderId(int $editingOrderId): void;
}