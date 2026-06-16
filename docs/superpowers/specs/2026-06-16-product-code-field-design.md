# Product Code Field — Design Spec

## Context

The Product module under Libraries (`/libraries/products`) currently identifies products only by brand + pack size + unit (see `ProductResource::toArray()`). There is no independent product code/SKU. This spec adds a manually-entered, required, unique `code` field to products.

This work follows the removal of the unused `price` field from the same module (no relation, just recent history on this module).

## Requirements

- **Generation:** Manually entered by the user (not auto-generated).
- **Validation:** Required and unique across all products.
- **Format:** Uppercase letters and digits only, no separators (e.g. `PRD1234`). Max length 50.
- **Display:** Shown as a table column in both product list views, and included in keyword search.

## Schema & Model

- New migration adds `code` (`string`, length 50) to the `products` table.
- Since existing rows have no code, the migration backfills them with a generated placeholder (`PROD` + zero-padded id, e.g. `PROD000007`) in the same migration, before adding the `NOT NULL` constraint and a unique index. This satisfies the required+unique constraint without breaking existing data. Users can rename these placeholders manually later via the edit modal.
- `App\Models\Product`: add `code` to `$fillable`.

## Validation (`App\Http\Requests\Libraries\ProductRequest`)

- Add rule: `'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9]+$/', Rule::unique('products', 'code')->ignore($this->id)]`.
- `Rule::unique(...)->ignore($this->id)` is required so editing a product without changing its code doesn't self-trigger a uniqueness failure.
- **Adjacent fix:** The existing `withValidator` duplicate check (brand_id + unit_id + pack_size) in this same request has the identical bug — it doesn't exclude the record being edited, so saving an edit without changing those three fields always self-matches and blocks the update with a false "duplicate" error. Since this spec is already touching this file and introducing the correct pattern for `code`, this existing check will be fixed the same way (exclude `$this->id` from the match).
- Frontend uppercases the input value as the user types, so user input always satisfies the regex without a confusing rejection from mixed-case input.

## Backend wiring

- `App\Services\Libraries\ProductClass::save()` / `update()`: pass `code` through to `Product::create()` / `update()`.
- `App\Services\Libraries\ProductClass::lists()`: add `->orWhere('code', 'LIKE', "%{$keyword}%")` to the existing keyword search alongside brand name and pack_size.
- `App\Http\Resources\Libraries\ProductResource`: add `'code' => $this->code` to the returned array.

## Frontend

- `resources/js/Pages/Modules/Inventory/Modal/CreateProductModal.vue`: add a "Product Code" text input above the Brand field. Auto-uppercases on input, shows `form.errors.code`, included in the `useForm` payload and in `edit()`/`show()` resets.
- `resources/js/Pages/Modules/Inventory/Tab/ProductsTab.vue`: add a "Code" column as the first column (before Name).
- `resources/js/Pages/Modules/Libraries/Products/Index.vue`: add the same "Code" column as the first column (before Name).

## Out of scope

- No change to how `name` is composed in `ProductResource` (code is not prefixed into the name).
- No bulk code-assignment tool for backfilled placeholder codes — users rename them manually as needed.
- No changes to the standalone-page-vs-tab duplication (`ProductsTab.vue` vs `Libraries/Products/Index.vue`) beyond adding the same column to both — that's a pre-existing structural issue, not part of this spec.
