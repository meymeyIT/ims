<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Category name <span class="text-danger">*</span></label>
        <input id="name" type="text" name="name" value="{{ old('name', $category->name) }}"
               class="form-control @error('name') is-invalid @enderror" required maxlength="100" autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea id="description" name="description" rows="3" maxlength="1000"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $category->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $category->is_active))>
            <label class="form-check-label" for="is_active">Active (available when creating products)</label>
        </div>
    </div>
</div>
