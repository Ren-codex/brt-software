# Inventory Stock Conversion / Repacking Feature

**Date:** 2026-06-21
**Scope:** Add a "Convert / Repack" action to the Inventory Stock detail page. A user selects a target product, specifies how many source units to consume and the conversion ratio, and the system deducts from the source batch and creates a new output inventory stock batch.

---

## Schema

### New table: `product_conversions`
| Column | Type | Notes |
|---|---|---|
| id | int PK | |
| source_stock_id | FK → inventory_stocks | Batch converted FROM |
| output_stock_id | FK → inventory_stocks (nullable) | Created output batch |
| source_qty_used | int | Units consumed |
| conversion_ratio | decimal(8,4) | 1 source = N output |
| output_quantity | int | source_qty_used × ratio |
| reason | text nullable | |
| converted_by_id | FK → users | |
| conversion_date | date | |
| timestamps | | |

### Altered table: `inventory_stocks`
- `received_item_id` → nullable (existing rows unaffected)
- add `product_id` nullable FK → products (for conversion output batches)
- add `conversion_id` nullable FK → product_conversions (marks batch as output)

---

## Backend

- **Model:** `ProductConversion` with belongsTo sourceStock, outputStock, convertedBy
- **InventoryStocks model:** add `product_id`, `conversion_id` to fillable; add `product()`, `conversion()`, `conversionsOut()` relations
- **Controller:** `ProductConversionController@store` — deduct source, create output batch, create conversion record, log two InventoryAdjustments
- **Route:** `POST /inventory/stock-conversions`
- **InventoryStockResource:** expose product, conversion (with source stock), conversionsOut
- **InventoryStockClass view():** eager load conversion and conversionsOut

---

## Frontend

- **`View.vue`:** Add "Convert / Repack" button (when qty > 0); add Conversion History table; replace Received Info with Conversion Info on converted batches
- **New `ConvertStockModal.vue`:** Target product dropdown, qty to convert, ratio, computed output qty, retail/wholesale price for new batch, expiration date, reason
