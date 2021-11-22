<?php

namespace Lab4u\Sale;

use Bitrix\Currency\CurrencyManager;
use Bitrix\Sale;
use Bitrix\Sale\Fuser;
use CSaleUser;
use Bitrix\Main;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Context;
use Bitrix\Main\NotImplementedException;
use Bitrix\Main\NotSupportedException;
use Bitrix\Main\ObjectException;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\ArgumentNullException;
use CUser;
use Lab4u\Helpers\UserHelper;
use Lab4u\Sale\Bonus\BonusHelper;
use Lab4u\Site;
use Lab4u\Core\Container;
use Lab4u\Iblock\AnalysisRepositoryInterface;
use Lab4u\Iblock\BioMaterialRepositoryInterface;
use Lab4u\Iblock\DecryptRepositoryInterface;
use Lab4u\Iblock\SubscriptionDbRepository;
use Lab4u\Sale\Basket\Calculation\BasketItemsBuilder;
use Lab4u\Sale\Basket\ItemBuilder;
use Lab4u\Sale\Basket\Lab4uItemInterface;
use Lab4u\Sale\Bonus\BonusManager;
use Lab4u\Sale\Bonus\BonusUserOption;
use Lab4u\User\Subscription;


class BasketDbRepository implements BasketRepositoryInterface
{
    protected $arProducts = [];

    /** @var Sale\Basket $basket */
    private $basket;

    /** @var AnalysisRepositoryInterface */
    private $analysisRepository;

    /** @var BioMaterialRepositoryInterface */
    private $bioMaterialRepository;
    /**
     * @var DecryptRepositoryInterface
     */
    private $decryptRepository;

    /**
     * @var ItemBuilder
     */
    private $itemBuilder;

    /**
     * @var BonusUserOption
     */
    private $bonusUserOption;

    /**
     * @var BonusManager
     */
    private $bonusManager;

    /**
     * @var int
     */
    private $userId;

    /**
     * @var int
     */
    private $editingOrderId;


    public function __construct(
        AnalysisRepositoryInterface $analysisRepository,
        BioMaterialRepositoryInterface $bioMaterialRepository,
        DecryptRepositoryInterface $decryptRepository,
        ItemBuilder $itemBuilder,
        BonusUserOption $bonusUserOption,
        BonusManager $bonusManager
    )
    {
        $this->analysisRepository = $analysisRepository;
        $this->bioMaterialRepository = $bioMaterialRepository;
        $this->decryptRepository = $decryptRepository;
        $this->itemBuilder = $itemBuilder;
        $this->bonusManager = $bonusManager;
        $this->bonusUserOption = $bonusUserOption;
    }

    /**
     * @inheritDoc
     */
    public function loadAnalysis(): array
    {
        $arAnalysis = [];
        $basketItems = $this->getBasketItems();

        /** @var Sale\BasketItem $basketItem */
        foreach ($basketItems as $basketItem) {
            if ($this->analysisRepository->isAnalysis(intval($basketItem->getProductId()))) {
                $arAnalysis[] = $basketItem;
            }
        }

        return $arAnalysis;
    }

    /**
     * @inheritDoc
     */
    public function loadBiomaterial(): array
    {
        $arBioMaterial = [];

        $basketItems = $this->getBasketItems();

        /** @var Sale\BasketItem $basketItem */
        foreach ($basketItems as $basketItem) {
            if ($this->bioMaterialRepository->isBioMaterial(intval($basketItem->getProductId()))) {
                $arBioMaterial[] = $basketItem;
            }
        }

        return $arBioMaterial;
    }

    public function loadDecrypt(): array
    {
        $arDecrypt = [];

        $basketItems = $this->getBasketItems();

        /** @var Sale\BasketItem $basketItem */
        foreach ($basketItems as $basketItem) {
            if ($this->decryptRepository->isDecrypt(intval($basketItem->getProductId()))) {
                $arDecrypt[] = $basketItem;
            }
        }

        return $arDecrypt;
    }

    /**
     * @return Sale\BasketItem|BasketItem|null
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentTypeException
     * @throws NotImplementedException
     */
    public function loadSubscription()
    {
        $basket = $this->getBasketInstance();

        $subscriptionBasketItem = $basket->getExistsItem('catalog', SubscriptionDbRepository::BITRIX_ELEMENT_ID);

        if ($subscriptionBasketItem instanceof Sale\BasketItem) {
            return $subscriptionBasketItem;
        }

        return null;
    }

    /**
     * Удаляет любой товар из корзины по PRODUCT_ID.
     *
     * @param array $arProductIds
     * @throws ArgumentException
     * @throws ArgumentOutOfRangeException
     * @throws ArgumentTypeException
     * @throws NotImplementedException
     * @throws ObjectNotFoundException
     */
    public function delete(array $arProductIds)
    {
        $basket = $this->getBasketInstance();

        foreach ($arProductIds as $productId) {

            $basketItem = $basket->getExistsItem('catalog', $productId);

            if (!($basketItem instanceof Sale\BasketItem)) {
                continue;
            }

            $basketItem->delete();
        }
    }

    /**
     * @param Sale\Basket|null $basket
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     * @throws ArgumentTypeException
     * @throws NotImplementedException
     * @throws ObjectNotFoundException
     */
    public function save(Sale\Basket $basket = null)
    {
        if (!is_null($basket)) {
            $this->basket = $basket;
        }

        $basket = $this->getBasketInstance();

        if ($this->isBasketEmpty()) {
            $basket->clearCollection();
            Sale\DiscountCouponsManager::clear(true);
            $basket->save();
            return;
        }

        $this->beforeSave();

        $basket->save();

        $this->afterSave();
    }

    /**
     * @throws \Exception
     */
    private function beforeSave(): void
    {
        $this->initUserId();

        $basket = $this->getBasketInstance();

        $this->updateBioMaterial();

        // $this->addSubscription();

        BonusHelper::$applyBonusRule = false;

        $this->initializeBasketOrderIfNotExists($basket);

        $basket->refresh(); // Если этого не делать, то цена бандла региональная сбрасывается на цену,
        // которую бы вернул стандратный провайдер.

        if ($this->userId) {
            $this->initEditingOrderId();
            $this->saveBonusAmountToUserOptions(); // Считаем и сохраняем бонусы.
            BonusHelper::$applyBonusRule = true;
            $basket->refresh(); // Делаем скидку на сумму бонусов
        }
    }

    private function initUserId(): void
    {
        $this->userId = $this->getUserId();
    }

    private function initEditingOrderId(): void
    {
        if (isset($this->editingOrderId) && $this->editingOrderId > 0) {
            return;
        }

        $this->editingOrderId = 0;

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = Container::find(OrderRepositoryInterface::class);
        $arEditingOrder = $orderRepository->getEditing($this->userId);

        if (isset($arEditingOrder) && is_array($arEditingOrder)) {
            $this->editingOrderId = (int)$arEditingOrder['ID'];
        }
    }

    protected function saveBonusAmountToUserOptions(): void
    {
        $basket = $this->getBasketInstance();

        BonusHelper::$bonusTotalDiscount = $this->bonusManager->setUserId($this->userId)
            ->setBasketSum($basket->getPrice())
            ->setEditingOrderId($this->editingOrderId)
            ->getTotalBonusDiscount();

        if (BonusHelper::$bonusTotalDiscount > 0) {
            $this->bonusUserOption->setTotalBonusDiscountSum(BonusHelper::$bonusTotalDiscount);
        } else {
            $this->bonusUserOption->unSetTotalBonusDiscountSum();
        }

        $bonusWithdrawAmount = $this->bonusManager->getBonusWithdrawAmount();

        if ($bonusWithdrawAmount > 0) {
            $this->bonusUserOption->setWithdrawSum($bonusWithdrawAmount);
        } else {
            $this->bonusUserOption->unSetWithdrawSum();
        }

        $bonusDepositAmount = $this->bonusManager->getBonusDepositAmount();

        if ($bonusDepositAmount > 0) {
            $this->bonusUserOption->setDepositSum($bonusDepositAmount);
        } else {
            $this->bonusUserOption->unSetDepositSum();
        }
    }

    private function afterSave(): void
    {
        $this->refreshPricesByComparison();
        $this->logDoubleQuantity();
    }

    private function refreshPricesByComparison(): void
    {
        /** @var BasketItemsBuilder $calculationBasketItemsBuilder */
        $calculationBasketItemsBuilder = Container::find(BasketItemsBuilder::class);
        $arBasketCalculationItems = $calculationBasketItemsBuilder->setBasket($this->getBasketInstance())->get();

        $zeroMaster = new \Lab4u\Sale\Basket\Calculation\ZeroMaster($arBasketCalculationItems);
        $zeroResult = $zeroMaster->getResult();

        if (empty($zeroResult)) {
            return;
        }


        foreach ($zeroResult as $basketId => $itemPrice) {
            Sale\Internals\BasketTable::update(
                $basketId,
                ['PRICE' => $itemPrice]
            );
        }
    }

    /**
     * Temp method for debugging task 10820
     */
    private function logDoubleQuantity(): void
    {
        if (Site::isDevSiteVersion()) {
            return;
        }

        $basket = $this->getBasketInstance();

        $arQuantityList = $basket->getQuantityList();

        foreach ($arQuantityList as $itemQuantity) {
            if ($itemQuantity > 1) {

                $fUserId = (string)Fuser::getId();
                $userId = (string)UserHelper::getUserId();

                $logFile = '/log/duplicate_quantity_error.log';

                $request = Main\Application::getInstance()->getContext()->getRequest();

                $arQueryList = $request->getQueryList()->toArray();
                $arPostList = $request->getPostList()->toArray();
                $requestUri = $request->getRequestUri();

                Main\Diag\Debug::writeToFile(date('d.m.Y H:i:s') . " | FUSER: $fUserId, USER_ID: $userId, URI: $requestUri \n\r", '', $logFile);
                Main\Diag\Debug::dumpToFile($arQueryList, '$arQueryList', $logFile);
                Main\Diag\Debug::dumpToFile($arPostList, '$arPostList', $logFile);

                $message = sprintf('Обнаружено количество у анализа больше 1. Проверь лог: %s', $logFile);

                /** @var \Lab4u\Telegram\Handler $telegramHandler */
                $telegramHandler = Container::find(\Lab4u\Telegram\Handler::class);
                $telegramHandler->sendMessage($message);

                break;
            }
        }
    }

    public function clearCoupon()
    {
        \Bitrix\Sale\DiscountCouponsManager::clear(true);
        $basket = $this->getBasketInstance();
        $basket->save();
    }

    public function getAnalysisIds(): array
    {
        $arAnalysisIds = [];

        $basketItems = $this->getBasketItems();

        /** @var Sale\BasketItem $basketItem */
        foreach ($basketItems as $basketItem) {

            $productId = intval($basketItem->getProductId());

            if (!$this->analysisRepository->isAnalysis($productId)) {
                continue;
            }

            $arAnalysisIds[] = $productId;
        }

        return $arAnalysisIds;
    }

    public function getBioMaterialIds(): array
    {
        $arBioMaterialIds = [];

        $basketItems = $this->getBasketItems();

        /** @var Sale\BasketItem $basketItem */
        foreach ($basketItems as $basketItem) {

            $productId = intval($basketItem->getProductId());

            if (!$this->bioMaterialRepository->isBioMaterial($productId)) {
                continue;
            }

            $arBioMaterialIds[] = $productId;
        }

        return $arBioMaterialIds;
    }

    /**
     * Обновляет биоматериалы в корзине.
     *
     * @throws ArgumentNullException
     * @throws ArgumentException
     * @throws ArgumentOutOfRangeException
     * @throws ArgumentTypeException
     * @throws NotImplementedException
     * @throws NotSupportedException
     * @throws ObjectNotFoundException
     */
    private function updateBioMaterial()
    {
        $arBioMaterialInBasket = $this->getBioMaterialIds();

        $arBioMaterialForAnalysisInBasket = $this->bioMaterialRepository->getForAnalysis($this->getAnalysisIds());

        $arBioMaterialToDelete = array_diff($arBioMaterialInBasket, $arBioMaterialForAnalysisInBasket);

        $arBioMaterialToAdd = array_diff($arBioMaterialForAnalysisInBasket, $arBioMaterialInBasket);

        $this->deleteInternal($arBioMaterialToDelete);

        $this->addBioMaterialInternal($arBioMaterialToAdd);
    }

    /**
     * @param $arBioMaterialIds
     * @throws ArgumentException
     * @throws ArgumentOutOfRangeException
     * @throws ArgumentTypeException
     * @throws NotImplementedException
     * @throws NotSupportedException
     * @internal не использовать нигде, кроме метода updateBioMaterial()
     */
    private function addBioMaterialInternal($arBioMaterialIds)
    {
        if (!isset($arBioMaterialIds) || empty($arBioMaterialIds)) {
            return;
        }

        if (!is_array($arBioMaterialIds)) {
            $arBioMaterialIds = [$arBioMaterialIds];
        }

        $basket = $this->getBasketInstance();

        $arProduct = [
            'CURRENCY' => CurrencyManager::getBaseCurrency(),
            'LID' => Context::getCurrent()->getSite(),
            'QUANTITY' => 1
        ];

        foreach ($arBioMaterialIds as $bioMaterialProductId) {
            $bioMaterialItem = $this->itemBuilder->build($bioMaterialProductId);

            $arProduct['PRODUCT_PROVIDER_CLASS'] = $bioMaterialItem->getCatalogProviderClass();

            $item = $basket->createItem('catalog', $bioMaterialItem->getId());
            $item->setFields($arProduct);
        }
    }

    /**
     * Удаляет из корзины Битрикс по PRODUCT_ID
     *
     * @param $arProductIds - ID или массив ID элементов.
     * @throws ArgumentNullException
     * @throws ArgumentException
     * @throws ArgumentOutOfRangeException
     * @throws NotImplementedException
     * @throws ObjectNotFoundException
     */
    private function deleteInternal($arProductIds)
    {
        if (!isset($arProductIds) || empty($arProductIds)) {
            return;
        }

        if (!is_array($arProductIds)) {
            $arProductIds = [$arProductIds];
        }

        $basket = $this->getBasketInstance();

        foreach ($arProductIds as $productId) {
            $basketItem = $basket->getExistsItem('catalog', $productId);

            if ($basketItem instanceof Sale\BasketItem) {
                $basketItem->delete();
            }
        }
    }

    protected function getUserId()
    {
        global $USER;

        return $USER instanceof CUser ? $USER->GetID() : null;
    }

    /**
     * @param \Bitrix\Sale\Basket $basket
     * @throws ArgumentNullException
     * @throws ArgumentException
     * @throws ArgumentOutOfRangeException
     * @throws NotImplementedException
     * @throws NotSupportedException
     * @throws ObjectException
     * @throws ObjectNotFoundException
     */
    protected function initializeBasketOrderIfNotExists(Sale\Basket $basket)
    {
        if (!$basket->getOrder()) {
            $userId = $this->getUserId() ?: CSaleUser::GetAnonymousUserID();

            $siteId = Context::getCurrent()->getSite();
            if (!isset($siteId)) {
                $siteId = Site::SITE_ID;
            }

            $registry = Sale\Registry::getInstance(Sale\Registry::REGISTRY_TYPE_ORDER);
            /** @var Sale\Order $orderClass */
            $orderClass = $registry->getOrderClassName();

            $order = $orderClass::create($siteId, $userId);

            $order->appendBasket($basket);

            $discounts = $order->getDiscount();
            $showPrices = $discounts->getShowPrices();
            if (!empty($showPrices['BASKET'])) {
                foreach ($showPrices['BASKET'] as $basketCode => $data) {
                    $basketItem = $basket->getItemByBasketCode($basketCode);
                    if ($basketItem instanceof Sale\BasketItemBase) {
                        $basketItem->setFieldNoDemand('BASE_PRICE', $data['SHOW_BASE_PRICE']);
                        $basketItem->setFieldNoDemand('PRICE', $data['SHOW_PRICE']);
                        $basketItem->setFieldNoDemand('DISCOUNT_PRICE', $data['SHOW_DISCOUNT']);
                    }
                }
            }
        }
    }

    /**
     * Возвращает инстанс корзины текущего пользователя.
     *
     * @return Sale\Basket
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws NotImplementedException
     */
    private function getBasketInstance(): Sale\Basket
    {
        if (isset($this->basket)) {
            return $this->basket;
        }

        $siteId = Main\Context::getCurrent()->getSite();
        if (!isset($siteId)) {
            $siteId = Site::SITE_ID;
        }

        $this->basket = \Bitrix\Sale\Basket::loadItemsForFUser(
            Fuser::getId(),
            $siteId
        );

        return $this->basket;
    }

    private function getBasketItems()
    {
        $basket = $this->getBasketInstance();

        return $basket->getBasketItems();
    }

    public function clear()
    {
        $basket = $this->getBasketInstance();
        $basket->clearCollection();
        $basket->save();
    }

    /**
     * Считаем корзину пустой, если в ней нет ни одного анализа.
     *
     * @return bool
     * @throws ArgumentNullException
     */
    public function isBasketEmpty(): bool
    {
        $basketItems = $this->getBasketItems();

        /** @var Sale\BasketItem $basketItem */
        foreach ($basketItems as $basketItem) {
            if ($this->analysisRepository->isAnalysis(intval($basketItem->getProductId()))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Lab4uItemInterface[] $arItems
     * @throws ArgumentException
     * @throws ArgumentOutOfRangeException
     * @throws ArgumentTypeException
     * @throws NotImplementedException
     * @throws NotSupportedException
     */
    public function add(array $arItems)
    {
        $basket = $this->getBasketInstance();

        $arProduct = [
            'CURRENCY' => CurrencyManager::getBaseCurrency(),
            'LID' => Context::getCurrent()->getSite(),
            'QUANTITY' => 1
        ];

        foreach ($arItems as $lab4uItem) {

            $item = $basket->getExistsItem('catalog', $lab4uItem->getId());

            if ($item) {
                continue;
            }

            $recommendationParts = explode('-', $lab4uItem->getFrom());
            $isRecommendation = array_shift($recommendationParts) === 'recommendation';
            $arProduct['RECOMMENDATION'] = $isRecommendation ? implode('-', $recommendationParts) : null;

            $arProduct['PRODUCT_PROVIDER_CLASS'] = $lab4uItem->getCatalogProviderClass();

            $item = $basket->createItem('catalog', $lab4uItem->getId());

            $item->setFields($arProduct);
        }
    }

    public function bioMaterialListIds(int $orderId): array
    {
        $arBiomaterialListIds = [];

        if ($orderId <= 0) {
            return $arBiomaterialListIds;
        }

        $order = Sale\Order::load($orderId);
        $basket = Sale\Basket::loadItemsForOrder($order);

        /** @var Sale\BasketItem $basketItem */
        foreach ($basket->getBasketItems() as $basketItem) {
            $productId = intval($basketItem->getProductId());

            if (!$this->bioMaterialRepository->isBioMaterial($productId)) {
                continue;
            }

            $arBiomaterialListIds[] = $productId;
        }

        return $arBiomaterialListIds;
    }

    public function bioMaterialListIdsByOrder(Sale\Order $order): array
    {
        $arBiomaterialListIds = [];

        /** @var Sale\BasketItem $basketItem */
        foreach ($order->getBasket()->getBasketItems() as $basketItem) {
            $productId = intval($basketItem->getProductId());

            if (!$this->bioMaterialRepository->isBioMaterial($productId)) {
                continue;
            }

            $arBiomaterialListIds[] = $productId;
        }

        return $arBiomaterialListIds;
    }

    /**
     * @inheritDoc
     */
    public function setEditingOrderId(int $editingOrderId): void
    {
        $this->editingOrderId = $editingOrderId;
    }

    /**
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     * @throws ArgumentTypeException
     * @throws Basket\BasketException
     * @throws NotImplementedException
     * @throws NotSupportedException
     * @throws ObjectNotFoundException
     */
    private function addSubscription()
    {
        if ($this->isBasketEmpty()) {
            return;
        }

        /** @var Subscription $subscription */
        $subscription = Container::find(Subscription::class);

        $isSubscriptionInBasket = $this->isSubscriptionInBasket();

        // @TODO Знать о том, надо ли абонементу себя пушить в корзину нельзя. Это не его ответственность.
        $isNeedPushToBasket = $subscription->isNeedPushToBasket();

        if (
            ($isSubscriptionInBasket && $isNeedPushToBasket)
            || (!$isSubscriptionInBasket && !$isNeedPushToBasket)
        ) {
            return;
        } elseif (!$isSubscriptionInBasket && $isNeedPushToBasket) {
            $basket = $this->getBasketInstance();

            $subscription = $this->itemBuilder->build(SubscriptionDbRepository::BITRIX_ELEMENT_ID);

            $arProduct = [
                'CURRENCY' => CurrencyManager::getBaseCurrency(),
                'LID' => Context::getCurrent()->getSite(),
                'QUANTITY' => 1,
                'PRODUCT_PROVIDER_CLASS' => $subscription->getCatalogProviderClass()
            ];

            $item = $basket->createItem('catalog', SubscriptionDbRepository::BITRIX_ELEMENT_ID);
            $item->setFields($arProduct);
        } elseif ($isSubscriptionInBasket && !$isNeedPushToBasket) {
            $this->delete([SubscriptionDbRepository::BITRIX_ELEMENT_ID]);
        }
    }

    private function isSubscriptionInBasket(): bool
    {
        $basket = $this->getBasketInstance();

        $basketItem = $basket->getExistsItem('catalog', SubscriptionDbRepository::BITRIX_ELEMENT_ID);

        return $basketItem instanceof Sale\BasketItem;
    }

}