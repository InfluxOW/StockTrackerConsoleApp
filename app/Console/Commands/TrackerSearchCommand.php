<?php

namespace App\Console\Commands;

use App\Clients\Helpers\SearchResults;
use App\Exceptions\ProductException;
use App\Retailer;

class TrackerSearchCommand extends Tracker
{
    protected $signature = 'tracker:search
    { retailer? : Retailer you want to use. Use `tracker:retailers` to check available retailers. }
    { product? : Name of the product you are looking for. }
    { --per=20 : Items per page <1-100> }
    { --page=1 : Current search page }
    { --filters= : Filter results by any params (e.g. `in_stock=true`) }
    { --sort=price.asc : Sort results by any params }
    { --attributes=sku,name,price,in_stock,url : Product attributes that you want to receive }';
    protected $description = 'Search for a product in the stock of the selected retailer';

    protected string $product;
    protected Retailer $retailer;
    protected SearchResults $results;

    public function isHidden()
    {
        return false;
    }

    public function handle()
    {
        $this->setInitialData();

        $this->search();

        $this->displayResults();

        $this->trackResults();
    }

    protected function setInitialData()
    {
        $this->retailer = $this->getRetailer(
            $this->argument('retailer') ?? $this->choice('Which retailer do you want to use?', $this->retailers())
        );

        $this->product = $this->argument('product') ?? $this->ask('What product are you looking for?');
    }

    protected function search()
    {
        $this->results = $this->retailer->client()->search(
            $this->product,
            $this->transformSearchOptions()
        );

    }

    protected function transformSearchOptions()
    {
        $optionsWithCorrectKeys = replaceArrayKeysWithMapper($this->options(), $this->retailer->client()->getSearchAttributes());
        $optionsWithCorrectKeysAndValues = replaceArrayValuesWithMapper($optionsWithCorrectKeys, $this->retailer->client()->getProductAttributes());
        return $this->addRequiredShowAttributes($optionsWithCorrectKeysAndValues);
    }

    protected function displayResults()
    {
        $this->table(
            array_keys($this->results->products[0]),
            $this->results->products
        );
        $this->info(json_encode($this->results->pagination));
    }

    protected function trackResults()
    {
        if ($this->confirm('Do you want to track one of the above products?')) {
            $this->track($this->chooseProductToTrack($this->results->products));

            while ($this->confirm('Do you want to track anything else?')) {
                $this->track($this->chooseProductToTrack($this->results->products));
            }
        }
    }

    protected function chooseProductToTrack($products)
    {
        $sku = $this->askWithValidation('Enter SKU of the product you want to track', 'sku', $this->productValidationRules()['sku']);
        $item = collect($products)->firstWhere('sku', $sku);

        throw_if(
            is_null($item),
            new ProductException("Product with SKU {$sku} has not been found in the search results")
        );

        return $item;
    }

    protected function track($item)
    {
        $this->call('tracker:add', [
            'retailer' => $this->retailer->name,
            'product' => [
                $item['name'],
                $item['sku'],
                $item['url'] ?? null,
                $item['price'] ?? null,
                $item['in_stock'] ?? null,
            ]
        ]);
    }

    protected function addRequiredShowAttributes(array $options)
    {
        if (! str_contains($options['show'], 'name')) {
            $options['show'] = "name," . $options['show'];
        }

        if (! str_contains($options['show'], 'sku')) {
            $options['show'] = "sku," . $options['show'];
        }

        return $options;
    }
}
