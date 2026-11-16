{{-- One purchase line. $index is a number, or "__INDEX__" inside the <template> used by JavaScript. --}}
@php
    $line = $line ?? [];
    $selectedId = (string) ($line['product_id'] ?? '');
@endphp
<tr data-line>
    <td style="min-width: 260px">
        <select name="items[{{ $index }}][product_id]" class="form-select @error("items.{$index}.product_id") is-invalid @enderror" data-product required>
            <option value="">Select a product…</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" data-price="{{ $product->purchase_price }}" data-unit="{{ $product->unit->abbreviation }}"
                        @selected($selectedId === (string) $product->id)>
                    {{ $product->name }} ({{ $product->sku }})
                </option>
            @endforeach
        </select>
        @error("items.{$index}.product_id")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </td>
    <td style="min-width: 140px">
        <div class="input-group has-validation">
            <input type="number" name="items[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '' }}" step="0.001" min="0.001"
                   class="form-control text-end @error("items.{$index}.quantity") is-invalid @enderror" data-qty required>
            <span class="input-group-text" data-unit-label>—</span>
            @error("items.{$index}.quantity")<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </td>
    <td style="min-width: 130px">
        <input type="number" name="items[{{ $index }}][unit_cost]" value="{{ $line['unit_cost'] ?? '' }}" step="0.0001" min="0"
               class="form-control text-end @error("items.{$index}.unit_cost") is-invalid @enderror" data-cost required>
        @error("items.{$index}.unit_cost")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </td>
    <td class="text-end fw-medium text-nowrap" data-line-total>0.00</td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-light text-danger" data-remove-line title="Remove line"><i class="bi bi-x-lg"></i></button>
    </td>
</tr>
