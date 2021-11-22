<?php

namespace Lab4u\Sale;


interface OrderRepositoryInterface
{

    public function getList(array $parameters);

    public function getOne(array $parameters);

    public function getEditing(int $userId);
}