<?php

namespace OttoAiMapper\Services;

/**
 * Class CatalogFieldService
 *
 * Provides the canonical lists of OTTO Market target fields
 * and PlentyONE source fields as plain public methods –
 * no reflection, no dynamic instantiation required.
 *
 * @package OttoAiMapper\Services
 */
class CatalogFieldService
{
    /**
     * Returns the list of standard OTTO Market catalog target fields.
     */
    public function getOttoFields(): array
    {
        return [
            ['key' => 'sku',                 'label' => 'SKU',                       'description' => 'Unique seller article number',            'required' => true,  'type' => 'string'],
            ['key' => 'productTitle',        'label' => 'Product Title',             'description' => 'Product name shown on OTTO',              'required' => true,  'type' => 'string'],
            ['key' => 'brandName',           'label' => 'Brand',                     'description' => 'Manufacturer brand name',                 'required' => true,  'type' => 'string'],
            ['key' => 'description',         'label' => 'Description',               'description' => 'Full product description',                'required' => true,  'type' => 'text'],
            ['key' => 'ean',                 'label' => 'EAN / GTIN',               'description' => 'European Article Number',                 'required' => true,  'type' => 'string'],
            ['key' => 'categoryPath',        'label' => 'Category',                  'description' => 'OTTO category path',                      'required' => true,  'type' => 'string'],
            ['key' => 'retailPrice',         'label' => 'Retail Price',              'description' => 'Selling price in EUR',                    'required' => true,  'type' => 'decimal'],
            ['key' => 'msrp',                'label' => 'Recommended Retail Price',  'description' => 'Manufacturer suggested retail price',     'required' => false, 'type' => 'decimal'],
            ['key' => 'stockQuantity',       'label' => 'Stock Quantity',            'description' => 'Available stock',                         'required' => true,  'type' => 'integer'],
            ['key' => 'mainImageUrl',        'label' => 'Main Image URL',            'description' => 'URL to the main product image',           'required' => true,  'type' => 'url'],
            ['key' => 'additionalImageUrls', 'label' => 'Additional Image URLs',     'description' => 'Comma-separated additional image URLs',   'required' => false, 'type' => 'string'],
            ['key' => 'color',               'label' => 'Color',                     'description' => 'Product color',                           'required' => false, 'type' => 'string'],
            ['key' => 'size',                'label' => 'Size',                      'description' => 'Product size (clothing/shoes)',           'required' => false, 'type' => 'string'],
            ['key' => 'weight',              'label' => 'Weight (kg)',               'description' => 'Product weight in kilograms',             'required' => false, 'type' => 'decimal'],
            ['key' => 'deliveryType',        'label' => 'Delivery Type',             'description' => 'Parcel, freight, etc.',                   'required' => true,  'type' => 'string'],
            ['key' => 'deliveryTime',        'label' => 'Delivery Time (days)',      'description' => 'Expected delivery time in business days', 'required' => false, 'type' => 'integer'],
            ['key' => 'materialComposition', 'label' => 'Material',                  'description' => 'Material composition in percent',         'required' => false, 'type' => 'string'],
            ['key' => 'countryOfOrigin',     'label' => 'Country of Origin',         'description' => 'ISO country code',                        'required' => false, 'type' => 'string'],
        ];
    }

    /**
     * Returns the list of available PlentyONE source fields.
     */
    public function getPlentyFields(): array
    {
        return [
            // Item
            ['key' => 'item.id',                           'label' => 'Item ID',                   'type' => 'integer'],
            ['key' => 'item.manufacturer.name',            'label' => 'Manufacturer Name',         'type' => 'string'],
            ['key' => 'item.manufacturer.countryOfOrigin', 'label' => 'Country of Origin',         'type' => 'string'],
            ['key' => 'item.condition',                    'label' => 'Item Condition',            'type' => 'string'],
            ['key' => 'item.description.name',             'label' => 'Item Name',                 'type' => 'string'],
            ['key' => 'item.description.shortDescription', 'label' => 'Short Description',         'type' => 'text'],
            ['key' => 'item.description.description',      'label' => 'Long Description',          'type' => 'text'],
            ['key' => 'item.description.technicalData',    'label' => 'Technical Data',            'type' => 'text'],
            ['key' => 'item.category.branch',              'label' => 'Category Branch',           'type' => 'string'],
            // Variation
            ['key' => 'variation.id',                      'label' => 'Variation ID',              'type' => 'integer'],
            ['key' => 'variation.number',                  'label' => 'Variation Number (SKU)',    'type' => 'string'],
            ['key' => 'variation.externalId',              'label' => 'External Variation ID',     'type' => 'string'],
            ['key' => 'variation.mainWarehouseId',         'label' => 'Main Warehouse ID',         'type' => 'integer'],
            ['key' => 'variation.weightG',                 'label' => 'Weight (g)',                'type' => 'decimal'],
            ['key' => 'variation.widthMm',                 'label' => 'Width (mm)',                'type' => 'decimal'],
            ['key' => 'variation.heightMm',                'label' => 'Height (mm)',               'type' => 'decimal'],
            ['key' => 'variation.lengthMm',                'label' => 'Length (mm)',               'type' => 'decimal'],
            // Barcode
            ['key' => 'barcode.code',                      'label' => 'Barcode (EAN/GTIN)',        'type' => 'string'],
            ['key' => 'barcode.type',                      'label' => 'Barcode Type',              'type' => 'string'],
            // Pricing
            ['key' => 'price.salesPrice',                  'label' => 'Sales Price',               'type' => 'decimal'],
            ['key' => 'price.rrp',                         'label' => 'Recommended Retail Price',  'type' => 'decimal'],
            ['key' => 'price.netPrice',                    'label' => 'Net Price',                 'type' => 'decimal'],
            // Stock
            ['key' => 'stock.physical',                    'label' => 'Physical Stock',            'type' => 'integer'],
            ['key' => 'stock.net',                         'label' => 'Net Stock',                 'type' => 'integer'],
            // Images
            ['key' => 'image.urlPreview',                  'label' => 'Image URL (Preview)',       'type' => 'url'],
            ['key' => 'image.url',                         'label' => 'Image URL (Full)',          'type' => 'url'],
            ['key' => 'image.position',                    'label' => 'Image Position',            'type' => 'integer'],
            // Properties / Attributes
            ['key' => 'property.color',                    'label' => 'Property: Color',           'type' => 'string'],
            ['key' => 'property.size',                     'label' => 'Property: Size',            'type' => 'string'],
            ['key' => 'property.material',                 'label' => 'Property: Material',        'type' => 'string'],
            ['key' => 'attribute.color.value',             'label' => 'Attribute: Color Value',    'type' => 'string'],
            ['key' => 'attribute.size.value',              'label' => 'Attribute: Size Value',     'type' => 'string'],
            // Shipping
            ['key' => 'shipping.profile.name',             'label' => 'Shipping Profile Name',     'type' => 'string'],
            ['key' => 'shipping.deliveryTime.min',         'label' => 'Min. Delivery Time (days)', 'type' => 'integer'],
            ['key' => 'shipping.deliveryTime.max',         'label' => 'Max. Delivery Time (days)', 'type' => 'integer'],
        ];
    }
}
