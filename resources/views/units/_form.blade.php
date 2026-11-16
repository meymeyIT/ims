<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Unit name <span class="text-danger">*</span></label>
        <input id="name" type="text" name="name" value="{{ old('name', $unit->name) }}"
               class="form-control @error('name') is-invalid @enderror" required maxlength="50" placeholder="e.g. Kilogram" autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="abbreviation" class="form-label">Abbreviation <span class="text-danger">*</span></label>
        <input id="abbreviation" type="text" name="abbreviation" value="{{ old('abbreviation', $unit->abbreviation) }}"
               class="form-control @error('abbreviation') is-invalid @enderror" required maxlength="20" placeholder="e.g. kg">
        @error('abbreviation')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <input id="description" type="text" name="description" value="{{ old('description', $unit->description) }}"
               class="form-control @error('description') is-invalid @enderror" maxlength="255">
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $unit->is_active))>
            <label class="form-check-label" for="is_active">Active (available when creating products)</label>
        </div>
    </div>
</div>
