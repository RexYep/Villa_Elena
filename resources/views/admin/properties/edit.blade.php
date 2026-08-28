@extends('layouts.admin')

@section('title', 'Edit Property — Villa Elena Admin')
@section('page-title', 'Edit Property')
@section('page-subtitle', $property->property_name)

@push('styles')
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
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

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            font-size: 11px;
            flex-shrink: 0;
        }

        .amenity-check.checked .check-icon {
            background: var(--terracotta);
            border-color: var(--terracotta);
            color: #fff;
        }

        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 24px 20px;
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
            font-size: 28px;
            color: var(--muted);
            display: block;
            margin-bottom: 6px;
        }

        .upload-zone p {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
        }

        .upload-zone small {
            font-size: 11px;
            color: var(--muted);
        }

        .image-previews {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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
            font-size: 11px;
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
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .existing-images {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }

        .existing-img-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 4/3;
        }

        .existing-img-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .delete-img-btn {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.9);
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

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
            font-size: 11px;
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

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .three-col {
                grid-template-columns: 1fr;
            }

            .amenities-grid {
                grid-template-columns: 1fr 1fr;
            }

            .image-previews {
                grid-template-columns: 1fr;
            }

            .existing-images {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.properties.index') }}">Properties</a>
        <span class="sep">›</span>
        <span class="current">Edit: {{ $property->property_name }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>Please fix the errors below.</div>
    @endif

    <form method="POST" action="{{ route('admin.properties.update', $property) }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="form-grid">
            <div>

                {{-- Basic Info --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-cyan"><i class="bi bi-info-circle"></i></div>
                        <h3>Basic Information</h3>
                    </div>
                    <div class="form-card-body">
                        @php $initialIsVilla = old('type', $property->type) === 'villa'; @endphp

                        <div class="mb-3">
                            <label class="form-label">Property Name
                                <span class="req" id="nameRequiredMark"
                                    style="display:{{ $initialIsVilla ? 'inline' : 'none' }};">*</span>
                            </label>
                            <input type="text" name="property_name"
                                class="form-control @error('property_name') is-invalid @enderror"
                                value="{{ old('property_name', $property->property_name) }}">
                            @error('property_name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type <span class="req">*</span></label>
                            <select name="type" id="typeSelect" class="form-select" required>
                                <option value="villa" {{ old('type', $property->type) == 'villa' ? 'selected' : '' }}>🏡 Villa
                                </option>
                                <option value="room" {{ old('type', $property->type) == 'room' ? 'selected' : '' }}>🛏️ Room
                                </option>
                            </select>
                            @if ($existingVilla)
                                <div id="villaWarning"
                                    style="display:none;margin-top:8px;padding:10px 12px;background:#fef9c3;border:1px solid #fde68a;border-radius:8px;font-size:12px;color:#a16207;">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Another master Villa already exists ("{{ $existingVilla->property_name }}"). Only one
                                    bookable whole-Villa record is allowed in the system.
                                </div>
                            @endif
                        </div>
                        <div class="two-col mb-3">
                            <div>
                                <label class="form-label">Max Guests <span class="req">*</span></label>
                                <input type="number" name="max_capacity" class="form-control"
                                    value="{{ old('max_capacity', $property->max_capacity) }}" min="1" required>
                            </div>
                            <div>
                                <label class="form-label">Floor Area (sqm)</label>
                                <input type="number" name="floor_area_sqm" class="form-control"
                                    value="{{ old('floor_area_sqm', $property->floor_area_sqm) }}" min="0"
                                    step="0.1">
                            </div>
                        </div>
                        <div id="villaOnlyDescription" style="display:{{ $initialIsVilla ? 'block' : 'none' }};">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control">{{ old('description', $property->description) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Pricing (Villa only) --}}
                <div class="form-card" id="villaOnlyPricing" style="display:{{ $initialIsVilla ? 'block' : 'none' }};">
                    <div class="form-card-header">
                        <div class="card-icon tag-amber"><i class="bi bi-tag"></i></div>
                        <h3>Pricing</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="two-col">
                            <div>
                                <label class="form-label">Base Price / Night <span class="req">*</span></label>
                                <div class="input-prefix">
                                    <span>₱</span>
                                    <input type="number" name="base_price" class="form-control"
                                        value="{{ old('base_price', $property->base_price) }}" min="0"
                                        step="0.01">
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Weekend Price / Night</label>
                                <div class="input-prefix">
                                    <span>₱</span>
                                    <input type="number" name="weekend_price" class="form-control"
                                        value="{{ old('weekend_price', $property->weekend_price) }}" min="0"
                                        step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Amenities (Villa only) --}}
                <div class="form-card" id="villaOnlyAmenities" style="display:{{ $initialIsVilla ? 'block' : 'none' }};">
                    <div class="form-card-header">
                        <div class="card-icon tag-green"><i class="bi bi-stars"></i></div>
                        <h3>Amenities</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="amenities-grid">
                            @php $selected = old('amenities', $property->amenities ?? []); @endphp
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

            {{-- RIGHT --}}
            <div>

                {{-- Current Images + Upload --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-purple"><i class="bi bi-images"></i></div>
                        <h3>Property Photos</h3>
                    </div>
                    <div class="form-card-body">

                        @if ($property->images->count() > 0)
                            <p class="text-muted-theme mb-12" style="font-size:12px;">
                                Current photos — click ✕ to remove
                            </p>
                            <div class="existing-images" id="existingImages">
                                @foreach ($property->images as $image)
                                    <div class="existing-img-item" data-image-id="{{ $image->id }}">
                                        <img src="{{ $image->url }}"
                                            alt="{{ $image->alt_text ?? $property->property_name }}">
                                        @if ($image->is_primary)
                                            <span class="primary-badge">Primary</span>
                                        @endif
                                        <button type="button" class="delete-img-btn" onclick="deleteImage(this)"
                                            title="Remove this photo">
                                            ✕
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <hr style="margin:20px 0; border-color:var(--border);">
                        @endif

                        <p class="text-muted-theme" style="font-size:12px;margin-bottom:10px;">
                            Add new photos:
                        </p>

                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="images[]" id="imageInput" multiple accept="image/*">
                            <i class="bi bi-cloud-upload"></i>
                            <p><strong>Click to upload</strong> or drag &amp; drop images</p>
                            <small>JPG, PNG, WEBP • Maximum 3MB per image</small>
                        </div>

                        <div class="image-previews" id="imagePreviews"></div>
                    </div>
                </div>
                {{-- Settings --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-amber"><i class="bi bi-sliders"></i></div>
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
                                    {{ old('is_featured', $property->is_featured) ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="form-card">
                    <div class="form-card-body">
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-check-circle me-2"></i> Save Changes
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
        // ── Villa Uniqueness Warning + Villa-Only Sections ──────────────
        const typeSelect = document.getElementById('typeSelect');
        const villaWarning = document.getElementById('villaWarning');

        function toggleVillaWarning() {
            if (!villaWarning) return;
            villaWarning.style.display = (typeSelect.value === 'villa') ? 'block' : 'none';
        }

        function updatePropertyTypeUI() {
            const isVilla = typeSelect.value === 'villa';
            ['villaOnlyPricing', 'villaOnlyAmenities', 'villaOnlyDescription'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = isVilla ? 'block' : 'none';
            });
            const mark = document.getElementById('nameRequiredMark');
            if (mark) mark.style.display = isVilla ? 'inline' : 'none';
            toggleVillaWarning();
        }
        typeSelect?.addEventListener('change', updatePropertyTypeUI);
        updatePropertyTypeUI();

        // Amenity checkboxes (fix: prevent default label behavior to avoid double-toggle)
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

        // Image Upload Preview
        const imageInput = document.getElementById('imageInput');
        const previewsContainer = document.getElementById('imagePreviews');
        let selectedFiles = [];

        imageInput.addEventListener('change', function(e) {
            const newFiles = Array.from(e.target.files);
            selectedFiles = [...selectedFiles, ...newFiles];
            renderPreviews();
        });

        function renderPreviews() {
            previewsContainer.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                <img src="${ev.target.result}" alt="preview">
                <button type="button" class="preview-remove" onclick="removeNewImage(${index})">✕</button>
            `;
                    previewsContainer.appendChild(div);
                };
                reader.readAsDataURL(file);
            });

            // Update the actual file input
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            imageInput.files = dt.files;
        }

        function removeNewImage(index) {
            selectedFiles.splice(index, 1);
            renderPreviews();
        }

        // Delete Existing Image (AJAX)
        const deleteImageUrlTemplate = '{{ route('admin.properties.images.destroy', ['image' => '__IMAGE_ID__']) }}';

        function deleteImage(btn) {
            if (!confirm('Are you sure you want to permanently delete this photo?')) {
                return;
            }

            const imageContainer = btn.closest('.existing-img-item');
            const imageId = imageContainer.getAttribute('data-image-id');

            fetch(deleteImageUrlTemplate.replace('__IMAGE_ID__', imageId), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    redirect: 'manual'
                })
                .then(response => {
                    // Server now returns a plain JSON 200 for this AJAX endpoint (no
                    // redirect), so response.ok reliably means "deleted". `redirect:
                // 'manual'` above additionally stops fetch from ever silently
                    // following a redirect into an opaque response if an older
                    // cached script or an unexpected 302 shows up (type would be
                    // 'opaqueredirect', status 0 — treat that as failure too).
                    if (response.ok && response.type !== 'opaqueredirect') {
                        imageContainer.style.transition = 'all 0.3s ease';
                        imageContainer.style.opacity = '0';
                        imageContainer.style.transform = 'scale(0.95)';

                        setTimeout(() => {
                            imageContainer.remove();
                        }, 300);
                    } else {
                        console.error('Delete image failed:', response.status, response.type);
                        alert('Failed to delete the image (HTTP ' + response.status + '). Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the image.');
                });
        }
    </script>
@endpush

