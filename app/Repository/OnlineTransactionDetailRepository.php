<?php

namespace App\Repository;

use App\Models\OnlineTransactionDetail;

class OnlineTransactionDetailRepository
{
    public function create(array $data)
    {
        return OnlineTransactionDetail::create($data);
    }

    public function find($id)
    {
        return OnlineTransactionDetail::find($id);
    }

    public function findByTransactionId($transactionId)
    {
        return OnlineTransactionDetail::where('transaction_id', $transactionId)->get();
    }

    public function update($id, array $data)
    {
        return OnlineTransactionDetail::where('id', $id)->update($data);
    }

    public function delete($id)
    {
        return OnlineTransactionDetail::destroy($id);
    }
}

