<?php

namespace Lab4u\Sale;

use Bitrix\Sale;
use Bitrix\Main;
use Lab4u\Sale\Order as L4Order;


class OrderDbRepository implements OrderRepositoryInterface
{

    public function getList(array $parameters)
    {
        if (!Main\Loader::includeModule('sale')) {
            return [];
        }

        $arResult = [];

        $db = Sale\Order::getList($parameters);

        while ($arOrder = $db->fetch()) {
            $arResult[] = $arOrder;
        }

        return $arResult;
    }

    public function getOne(array $parameters)
    {
        return Sale\Internals\OrderTable::getRow($parameters);
    }

    public function getEditing(int $userId)
    {
        $parameters = [
            'select' => ['ID'],
            'filter' => [
                'USER_ID' => $userId,
                'STATUS_ID' => L4Order::STATUS_ID_EDITING
            ]
        ];

        return $this->getOne($parameters);
    }
}