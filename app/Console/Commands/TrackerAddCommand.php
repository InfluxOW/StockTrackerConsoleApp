<?php

namespace App\Console\Commands;

use App\Exceptions\StockException;
use App\Product;
use \Illuminate\Validation\ValidationException;
use Validator;

class TrackerAddCommand extends Tracker
{
    protected $signature = 'tracker:add
    { retailer? : Name of the retailer you want to use }
    { product?* : Product details [required: name, sku; optional: url, price, in_stock] }';
    protected $description = 'Add new product to the tracker';

    public function isHidden()
    {
        return false;
    }

    public function handle()
    {
        $product = $this->createProduct();

        $this->info("Product {$product->name} has been tracked!");
    }

    protected function createProduct()
    {
        $retailer = $this->getRetailer(
            $this->argument('retailer') ?? $this->choice('Which retailer do you want to use?', $this->retailers())
        );
        $productAttributes = empty($this->argument('product')) ? $this->askAboutProduct() : $this->getProductAttributesFromCommandArguments();

        $product = Product::firstOrMake($productAttributes);

        $retailer->products()->save($product);

        return $product;
    }

    protected function askAboutProduct()
    {
        $rules = $this->productValidationRules();
        $attributes['name'] =  $this->askWithValidation('What product do you want to add?', 'name', $rules['name']);
        $attributes['sku'] = $this->askWithValidation('Enter SKU of the product', 'sku', $rules['sku']);

        if ($this->confirm('Do you want to add any additional product information?')) {
            $attributes['url'] = $this->askWithValidation('Enter url of the product', 'url', $rules['url']);
            $attributes['price'] = $this->askWithValidation('Enter price of the product in cents', 'price', $rules['price']);
            $attributes['in_stock'] = $this->confirm('Is product in stock?');
        }

        return $attributes;
    }


    protected function getProductAttributesFromCommandArguments()
    {
        $attributes = [
            'name' => $this->argument('product')[0] ?? null,
            'sku' => $this->argument('product')[1] ?? null,
            'url' => $this->argument('product')[2] ?? null,
            'price' => $this->argument('product')[3] ?? null,
            'in_stock' => $this->argument('product')[4] ?? null
        ];
        $validator = Validator::make($attributes, $this->productValidationRules());

        throw_if(
            $validator->fails(),
            ValidationException::withMessages($validator->getMessageBag()->toArray())
        );

        return $attributes;
    }
}
