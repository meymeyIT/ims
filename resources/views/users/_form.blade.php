@php
    $isEdit = $user->exists;
    $isSelf = $isEdit && auth()->user()->is($user);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}"
               class="form-control @error('name') is-invalid @enderror" required maxlength="255">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
        <div class="input-group has-validation">
            <span class="input-group-text">@</span>
            <input id="username" type="text" name="username" value="{{ old('username', $user->username) }}"
                   class="form-control @error('username') is-invalid @enderror" required minlength="3" maxlength="50"
                   autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="e.g. dara">
            @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">Used to sign in. Lowercase letters, numbers, dots, dashes, underscores.</div>
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}"
               class="form-control @error('email') is-invalid @enderror" required autocomplete="off">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label">Phone</label>
        <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}"
               class="form-control @error('phone') is-invalid @enderror" placeholder="012 345 678">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
        <select id="role_id" name="role_id" class="form-select @error('role_id') is-invalid @enderror" required>
            <option value="">Select a role…</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>
                    {{ $role->display_name }}
                </option>
            @endforeach
        </select>
        @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($isSelf)<div class="form-text">You cannot change your own role.</div>@endif
    </div>

    <div class="col-md-6">
        <label for="password" class="form-label">
            Password @unless ($isEdit)<span class="text-danger">*</span>@endunless
        </label>
        <div class="input-group has-validation">
            <input id="password" type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   autocomplete="new-password" @unless ($isEdit) required @endunless>
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Show password"><i class="bi bi-eye"></i></button>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">
            {{ $isEdit ? 'Leave blank to keep the current password. ' : '' }}Min. 8 characters with upper & lower case letters and a number.
        </div>
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input @error('is_active') is-invalid @enderror" type="checkbox" role="switch"
                   id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
            <label class="form-check-label" for="is_active">Active (can sign in)</label>
            @error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
