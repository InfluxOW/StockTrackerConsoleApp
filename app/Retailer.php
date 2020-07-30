<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Retailer extends Model
{
    protected $fillable = ['name'];

    public function addStock(Product $product, Stock $stock)
    {
        $stock->product()->associate($product);
        $this->stock()->save($stock);
    }

    public function stock()
    {
        return $this->hasMany(Stock::class);
    }
}
