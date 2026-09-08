@extends('layouts.admin')

@section('title', 'Add Property — Villa Elena Admin')
@section('page-title', 'Add New Property')
@section('page-subtitle', 'Fill in the details below to create a new property listing')

@push('styles')
    <style>
        /* Form Layout — minmax(0, …) on the flexible track, since a bare 1fr is
           minmax(auto, 1fr) and lets anything wide inside (the amenities grid
           below, a long validation message) push the column past its share. */
        .form-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 24px;
            align-items: start;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 110px;
        }

        .input-prefix {
            position: relative;
        }

        .input-prefix span {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
            pointer-events: none;
        }

        .input-prefix input {
            padding-left: 28px;
        }

        .three-col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        /* Amenities checkboxes.

           auto-fill rather than a fixed 3 columns. Above 900px the form column
           is 1fr of (1fr + 360px), so it gets narrow while the window is still
           wide — at a 1000px viewport it is about 316px, and three fixed columns
           are ~100px each. "Air Conditioning" cannot render narrower than its
           longest word plus the check icon and padding (~137px), so each track
           was forced past its share and the grid overflowed its own card. Sizing
           by content instead means the column count follows the space available:
           two when it is tight, five when the form is full width.

           130px is the measured floor: the widest chip, "Air Conditioning", has a
           min-content width of 125px including its check icon and padding, so any
           track at or above that can never be forced wider than its share. */
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 8px;
        }

        .amenity-check {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all .15s;
            font-size: 13px;
            color: var(--text-main);
        }

        .amenity-check:hover {
            border-color: var(--terracotta);
            background: var(--sand);
        }

        .amenity-check input {
            display: none;
        }

        .amenity-check.checked {
            border-color: var(--terracotta);
            background: var(--gold-dim);
            color: var(--terracotta);
            font-weight: 500;
        }

        .amenity-check .check-icon {
            width: 18px;
            height: 18px;
            border: 1.5px solid var(--border);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        .amenity-check.checked .check-icon {
            background: var(--terracotta);
            border-color: var(--terracotta);
            color: #fff;
        }

        /* Image Upload */
        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 28px 20px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            position: relative;
        }

        .upload-zone:hover,
        .upload-zone.drag-over {
            border-color: var(--terracotta);
            background: var(--sand);
        }

        .upload-zone input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .upload-zone i {
            font-size: 32px;
            color: var(--muted);
            display: block;
            margin-bottom: 8px;
        }

        .upload-zone p {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
        }

        .upload-zone small {
            font-size: 13px;
            color: var(--muted);
        }

        /* Image previews — two fixed columns only makes sense while this card is
           in the 360px sidebar. Once .form-grid stacks below 900px the card runs
           the full width and those two thumbnails become enormous. */
        .image-previews {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 12px;
        }

        .preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 4/3;
        }

        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .primary-badge {
            position: absolute;
            bottom: 4px;
            left: 4px;
            background: var(--gold);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Toggle Switch */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .toggle-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .toggle-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-main);
        }

        .toggle-label small {
            display: block;
            font-size: 13px;
            color: var(--muted);
            font-weight: 400;
        }

        .form-switch .form-check-input {
            width: 40px;
            height: 22px;
            cursor: pointer;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--terracotta);
            border-color: var(--terracotta);
        }

        /* Submit Buttons */
        .btn-submit {
            width: 100%;
        }

        .btn-cancel {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
            padding: 8px;
            border-radius: 8px;
            transition: background .2s;
        }

        .btn-cancel:hover {
            background: var(--sand);
            color: var(--text-main);
        }

        /* Collapses at 1150px, not 900px. The 360px photo/settings column is a
           fixed track, so between 993px — where the admin's own 260px sidebar
           reappears — and roughly 1150px the form gets whatever is left, and that
           is not much: measured 277px of form against 360px of sidebar at a
           1000px viewport, and 377px against 360px at 1100px. The secondary
           column was wider than the form it was meant to support, which squeezed
           the paired fields to 105px each. Stacking through that band gives the
           form the full ~676px instead. */
        @media (max-width: 1150px) {
            .form-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        /* admin.css collapses every .two-col below 900px — but 900px is exactly
           where .form-grid above drops its 360px sidebar and gives the form the
           full width, so the inner pairs collapsed at the moment their container
           grew. Same problem, and the same 561px threshold, as the booking form.
           Prefixed with .form-grid for specificity: admin.css's rule is a bare
           .two-col and `composer dev` injects it after this block. */
        @media (min-width: 561px) {
            .form-grid .two-col {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 560px) {
            .three-col {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>
@endpush

@section('content')

    {{-- Breadcrumb --}}
    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.properties.index') }}">Properties</a>
        <span class="sep">›</span>
        <span class="current">Add New</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            Please fix the following errors: {{ implode(', ', $errors->all()) }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.properties.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-grid">

            {{-- LEFT COLUMN --}}
            <div>

                {{-- Basic Info --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#e0f2fe; color:#0369a1;"><i class="bi bi-info-circle"></i>
                        </div>
                        <h3>Basic Information</h3>
                    </div>
                    <div class="form-card-body">

                        @php $initialIsVilla = !$existingVilla; @endphp

                        <div class="mb-3">
                            <label class="form-label">Property Name
                                <span class="req" id="nameRequiredMark"
                                    style="display:{{ $initialIsVilla ? 'inline' : 'none' }};">*</span>
                            </label>
                            <input type="text" name="property_name"
                                class="form-control @error('property_name') is-invalid @enderror"
                                value="{{ old('property_name') }}" placeholder="e.g. Villa Elena Suite A">
                            @error('property_name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        @if ($existingVilla)
                            <input type="hidden" name="type" value="room">
                            <div class="mb-3"
                                style="padding:10px 12px;background:var(--sand);border-radius:8px;font-size:13px;color:var(--text-main);">
                                <i class="bi bi-info-circle me-1"></i>
                                Adding a <strong>Room</strong> — part of "{{ $existingVilla->property_name }}". Villa Elena
                                only has one master Villa.
                            </div>
                        @else
                            <div class="mb-3">
                                <label class="form-label">Property Type <span class="req">*</span></label>
                                <select name="type" id="typeSelect"
                                    class="form-select @error('type') is-invalid @enderror" required>
                                    <option value="villa" selected>🏡 Villa</option>
                                    <option value="room" {{ old('type') == 'room' ? 'selected' : '' }}>🛏️ Room</option>
                                </select>
                                @error('type')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif

                        <div class="two-col mb-3">
                            <div>
                                <label class="form-label">Max Guests <span class="req">*</span></label>
                                <input type="number" name="max_capacity"
                                    class="form-control @error('max_capacity') is-invalid @enderror"
                                    value="{{ old('max_capacity') }}" min="1" placeholder="0" required>
                                @error('max_capacity')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Floor Area (sqm)</label>
                                <input type="number" name="floor_area_sqm" class="form-control"
                                    value="{{ old('floor_area_sqm') }}" min="0" step="0.1" placeholder="0">
                            </div>
                        </div>

                        <div id="villaOnlyDescription" style="display:{{ $initialIsVilla ? 'block' : 'none' }};">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" placeholder="Describe this property...">{{ old('description') }}</textarea>
                        </div>

                    </div>
                </div>

                {{-- Pricing (Villa only) --}}
                <div class="form-card" id="villaOnlyPricing" style="display:{{ $initialIsVilla ? 'block' : 'none' }};">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#fef9c3; color:#a16207;"><i class="bi bi-tag"></i></div>
                        <h3>Pricing</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="two-col">
                            <div>
                                <label class="form-label">Base Price / Night <span class="req">*</span></label>
                                <div class="input-prefix">
                                    <span>₱</span>
                                    <input type="number" name="base_price"
                                        class="form-control @error('base_price') is-invalid @enderror"
                                        value="{{ old('base_price') }}" min="0" step="0.01" placeholder="0.00">
                                </div>
                                @error('base_price')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Weekend Price / Night</label>
                                <div class="input-prefix">
                                    <span>₱</span>
                                    <input type="number" name="weekend_price" class="form-control"
                                        value="{{ old('weekend_price') }}" min="0" step="0.01"
                                        placeholder="0.00">
                                </div>
                                <small style="font-size: 13px; color:#94a3b8; margin-top:4px; display:block;">
                                    Applied on Saturdays &amp; Sundays
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Amenities (Villa only) --}}
                <div class="form-card" id="villaOnlyAmenities" style="display:{{ $initialIsVilla ? 'block' : 'none' }};">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#dcfce7; color:#15803d;"><i class="bi bi-stars"></i>
                        </div>
                        <h3>Amenities</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="amenities-grid" id="amenitiesGrid">
                            @php $selected = old('amenities', []); @endphp
                            @forelse($amenityList as $amenity)
                                <label class="amenity-check {{ in_array($amenity, $selected) ? 'checked' : '' }}">
                                    <input type="checkbox" name="amenities[]" value="{{ $amenity }}"
                                        {{ in_array($amenity, $selected) ? 'checked' : '' }}>
                                    <span class="check-icon">{{ in_array($amenity, $selected) ? '✓' : '' }}</span>
                                    <span>{{ $amenity }}</span>
                                </label>
                            @empty
                                <p class="text-muted-theme" style="font-size:13px;">
                                    No amenities configured yet — add some in Settings → Amenities.
                                </p>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>

            {{-- RIGHT COLUMN --}}
            <div>

                {{-- Image Upload --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#f3e8ff; color:#7c3aed;"><i class="bi bi-images"></i>
                        </div>
                        <h3>Photos</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="images[]" id="imageInput" multiple
                                accept="image/jpg,image/jpeg,image/png,image/webp">
                            <i class="bi bi-cloud-upload"></i>
                            <p><strong>Click to upload</strong> or drag and drop</p>
                            <small>JPG, PNG, WEBP — Max 3MB each</small>
                        </div>
                        <div class="image-previews" id="imagePreviews"></div>
                    </div>
                </div>

                {{-- Settings --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#fef9c3; color:#a16207;"><i class="bi bi-sliders"></i>
                        </div>
                        <h3>Settings</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="toggle-row">
                            <div class="toggle-label">
                                Featured Property
                                <small>Show on homepage highlights</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_featured" value="1"
                                    {{ old('is_featured') ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="form-card">
                    <div class="form-card-body">
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-plus-circle me-2"></i> Create Property
                        </button>
                        <a href="{{ route('admin.properties.index') }}" class="btn-cancel">
                            Cancel — go back to properties
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </form>

@endsection

@push('scripts')
    <script>
        // ── Show/Hide Villa-Only Sections Based On Selected Type ────────
        const typeSelect = document.getElementById('typeSelect');

        function updatePropertyTypeUI() {
            const isVilla = typeSelect ? typeSelect.value === 'villa' : false;
            ['villaOnlyPricing', 'villaOnlyAmenities', 'villaOnlyDescription'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = isVilla ? 'block' : 'none';
            });
            const mark = document.getElementById('nameRequiredMark');
            if (mark) mark.style.display = isVilla ? 'inline' : 'none';
        }
        typeSelect?.addEventListener('change', updatePropertyTypeUI);
        updatePropertyTypeUI();

        // ── Amenity Checkboxes ────────────────────────────────────────
        document.querySelectorAll('.amenity-check').forEach(label => {
            label.addEventListener('click', (e) => {
                e.preventDefault();
                const cb = label.querySelector('input[type="checkbox"]');
                const icon = label.querySelector('.check-icon');
                cb.checked = !cb.checked;
                label.classList.toggle('checked', cb.checked);
                icon.textContent = cb.checked ? '✓' : '';
            });
        });

        // ── Image Upload Preview ──────────────────────────────────────
        const input = document.getElementById('imageInput');
        const previews = document.getElementById('imagePreviews');
        const zone = document.getElementById('uploadZone');
        let fileList = [];

        input.addEventListener('change', handleFiles);

        zone.addEventListener('dragover', e => {
            e.preventDefault();
            zone.classList.add('drag-over');
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            handleFiles({
                target: {
                    files: e.dataTransfer.files
                }
            });
        });

        function handleFiles(e) {
            const newFiles = Array.from(e.target.files);
            fileList = [...fileList, ...newFiles];
            renderPreviews();
        }

        function renderPreviews() {
            previews.innerHTML = '';
            fileList.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = ev => {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                <img src="${ev.target.result}" alt="preview">
                ${index === 0 ? '<span class="primary-badge">Primary</span>' : ''}
                <button type="button" class="preview-remove" onclick="removeImage(${index})">✕</button>
            `;
                    previews.appendChild(div);
                };
                reader.readAsDataURL(file);
            });

            // Rebuild DataTransfer to keep file input in sync
            const dt = new DataTransfer();
            fileList.forEach(f => dt.items.add(f));
            input.files = dt.files;
        }

        function removeImage(index) {
            fileList.splice(index, 1);
            renderPreviews();
        }
    </script>
@endpush

