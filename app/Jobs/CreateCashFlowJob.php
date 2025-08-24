<?php

namespace App\Jobs;

use App\Repository\CashFlowRepository;

class CreateCashFlowJob
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(CashFlowRepository $cashFlowRepository)
    {
        $cashFlowRepository->create($this->data);
    }
}
