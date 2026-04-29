<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit Property — Villa Elena Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --navy:#0d1b2a;--navy-mid:#1a2f45;--gold:#c9a84c;--gold-light:#e8c97a;--gold-dim:rgba(201,168,76,0.15);--off-white:#f4f6f9;--border:#e2e8f0;--text-main:#1a2f45;--text-muted:#6b7a8d;--sidebar-w:260px;--topbar-h:68px; }
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--off-white);color:var(--text-main);}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--navy);display:flex;flex-direction:column;z-index:1000;overflow-y:auto;}
        .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,0.07);}
        .sidebar-brand h1{font-family:'Cormorant Garamond',serif;color:var(--gold-light);font-size:22px;font-weight:700;}
        .sidebar-brand p{color:rgba(255,255,255,0.35);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;margin-top:3px;}
        .sidebar-section{padding:20px 16px 8px;}
        .sidebar-section-label{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:0 8px;margin-bottom:6px;}
        .nav-item-custom{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:14px;transition:all .2s;margin-bottom:2px;}
        .nav-item-custom:hover{background:rgba(255,255,255,0.07);color:#fff;}
        .nav-item-custom.active{background:var(--gold-dim);color:var(--gold-light);font-weight:500;}
        .nav-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;background:rgba(255,255,255,0.05);}
        .nav-item-custom.active .nav-icon{background:var(--gold-dim);color:var(--gold);}
        .sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,0.07);}
        .user-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.05);}
        .user-avatar{width:36px;height:36px;border-radius:50%;background:var(--gold-dim);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:600;}
        .user-info .name{color:#fff;font-size:13px;font-weight:500;}
        .user-info .role-badge{font-size:10px;color:var(--gold);letter-spacing:0.5px;text-transform:uppercase;}
        .topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 32px;z-index:900;}
        .topbar-left h2{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;}
        .topbar-left p{font-size:12px;color:var(--text-muted);margin-top:1px;}
        .logout-btn{display:flex;align-items:center;gap:7px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:9px;padding:7px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none;}
        .logout-btn:hover{background:#ef4444;color:white;border-color:#ef4444;}
        .main-content{margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:32px;}
        .breadcrumb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);margin-bottom:24px;}
        .breadcrumb-row a{color:var(--text-muted);text-decoration:none;}
        .breadcrumb-row a:hover{color:var(--navy);}
        .breadcrumb-row .sep{color:#cbd5e1;}
        .breadcrumb-row .current{color:var(--text-main);font-weight:500;}
        .form-grid{display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;}
        .form-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .form-card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
        .form-card-header .card-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;}
        .form-card-header h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;color:var(--text-main);}
        .form-card-body{padding:24px;}
        .form-label{font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;display:block;}
        .form-label .req{color:#ef4444;margin-left:3px;}
        .form-control,.form-select{border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;font-size:14px;font-family:'DM Sans',sans-serif;width:100%;transition:border-color .2s,box-shadow .2s;background:#fff;}
        .form-control:focus,.form-select:focus{outline:none;border-color:var(--navy-mid);box-shadow:0 0 0 3px rgba(26,47,69,0.08);}
        .form-control.is-invalid{border-color:#ef4444;}
        .invalid-feedback{font-size:12px;color:#ef4444;margin-top:4px;display:block;}
        textarea.form-control{resize:vertical;min-height:110px;}
        .input-prefix{position:relative;}
        .input-prefix span{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;pointer-events:none;}
        .input-prefix input{padding-left:28px;}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
        .amenities-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
        .amenity-check{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;transition:all .15s;font-size:13px;}
        .amenity-check:hover{border-color:var(--navy-mid);background:#f8fafc;}
        .amenity-check input{display:none;}
        .amenity-check.checked{border-color:var(--navy);background:#f0f4f8;color:var(--navy);font-weight:500;}
        .amenity-check .check-icon{width:18px;height:18px;border:1.5px solid #cbd5e1;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
        .amenity-check.checked .check-icon{background:var(--navy);border-color:var(--navy);color:#fff;}
        .upload-zone{border:2px dashed var(--border);border-radius:10px;padding:24px 20px;text-align:center;cursor:pointer;transition:all .2s;position:relative;}
        .upload-zone:hover,.upload-zone.drag-over{border-color:var(--navy-mid);background:#f8fafc;}
        .upload-zone input{position:absolute;inset:0;opacity:0;cursor:pointer;}
        .upload-zone i{font-size:28px;color:#94a3b8;display:block;margin-bottom:6px;}
        .upload-zone p{font-size:13px;color:var(--text-muted);margin:0;}
        .upload-zone small{font-size:11px;color:#94a3b8;}
        .image-previews{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:12px;}
        .preview-item{position:relative;border-radius:8px;overflow:hidden;aspect-ratio:4/3;}
        .preview-item img{width:100%;height:100%;object-fit:cover;}
        .preview-remove{position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,0.6);color:#fff;border:none;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;}
        .primary-badge{position:absolute;bottom:4px;left:4px;background:var(--gold);color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;letter-spacing:0.5px;text-transform:uppercase;}
        .existing-images{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:12px;}
        .existing-img-item{position:relative;border-radius:8px;overflow:hidden;aspect-ratio:4/3;}
        .existing-img-item img{width:100%;height:100%;object-fit:cover;}
        .toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f1f5f9;}
        .toggle-row:last-child{border-bottom:none;padding-bottom:0;}
        .toggle-label{font-size:13px;font-weight:500;color:var(--text-main);}
        .toggle-label small{display:block;font-size:11px;color:var(--text-muted);font-weight:400;}
        .form-switch .form-check-input{width:40px;height:22px;cursor:pointer;}
        .form-switch .form-check-input:checked{background-color:var(--navy);border-color:var(--navy);}
        .btn-submit{background:var(--navy);color:#fff;border:none;border-radius:9px;padding:12px 24px;font-size:14px;font-weight:600;width:100%;cursor:pointer;transition:opacity .2s;font-family:'DM Sans',sans-serif;}
        .btn-submit:hover{opacity:0.88;}
        .btn-cancel{display:block;text-align:center;margin-top:10px;color:var(--text-muted);font-size:13px;text-decoration:none;padding:8px;border-radius:8px;transition:background .2s;}
        .btn-cancel:hover{background:#f1f5f9;color:var(--text-main);}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-danger{background:#fee2e2;color:#dc2626;}
        .alert-success{background:#dcfce7;color:#15803d;}
        .delete-img-btn{position:absolute;top:4px;right:4px;width:24px;height:24px;border-radius:50%;background:rgba(239,68,68,0.9);color:#fff;border:none;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;}
    </style>
</head>
<body>

@include('admin.partials.sidebar')


<header class="topbar">
    <div class="topbar-left">
        <h2>Edit Property</h2>
        <p>{{ $property->property_name }}</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
        </form>
    </div>
</header>

<main class="main-content">

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.properties.index') }}">Properties</a>
        <span class="sep">›</span>
        <span class="current">Edit: {{ $property->property_name }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>Please fix the errors below.</div>
    @endif

    <form method="POST" action="{{ route('admin.properties.update', $property) }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="form-grid">
            <div>

                {{-- Basic Info --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#e0f2fe;color:#0369a1;"><i class="bi bi-info-circle"></i></div>
                        <h3>Basic Information</h3>
                    </div>
                    <div class="form-card-body">
                        <div style="margin-bottom:16px;">
                            <label class="form-label">Property Name <span class="req">*</span></label>
                            <input type="text" name="property_name" class="form-control @error('property_name') is-invalid @enderror"
                                value="{{ old('property_name', $property->property_name) }}" required>
                            @error('property_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="two-col" style="margin-bottom:16px;">
                            <div>
                                <label class="form-label">Type <span class="req">*</span></label>
                                <select name="type" class="form-select" required>
                                    <option value="villa"    {{ old('type',$property->type)=='villa'    ?'selected':'' }}>🏡 Villa</option>
                                    <option value="cottage"  {{ old('type',$property->type)=='cottage'  ?'selected':'' }}>🏠 Cottage</option>
                                    <option value="room"     {{ old('type',$property->type)=='room'     ?'selected':'' }}>🛏️ Room</option>
                                    <option value="hall"     {{ old('type',$property->type)=='hall'     ?'selected':'' }}>🏛️ Hall</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Status <span class="req">*</span></label>
                                <select name="status" class="form-select" required>
                                    <option value="available"   {{ old('status',$property->status)=='available'   ?'selected':'' }}>✅ Available</option>
                                    <option value="occupied"    {{ old('status',$property->status)=='occupied'    ?'selected':'' }}>🔵 Occupied</option>
                                    <option value="maintenance" {{ old('status',$property->status)=='maintenance' ?'selected':'' }}>🔧 Maintenance</option>
                                </select>
                            </div>
                        </div>
                        <div class="three-col" style="margin-bottom:16px;">
                            <div>
                                <label class="form-label">Max Guests <span class="req">*</span></label>
                                <input type="number" name="max_capacity" class="form-control"
                                    value="{{ old('max_capacity', $property->max_capacity) }}" min="1" required>
                            </div>
                            <div>
                                <label class="form-label">Floor Area (sqm)</label>
                                <input type="number" name="floor_area_sqm" class="form-control"
                                    value="{{ old('floor_area_sqm', $property->floor_area_sqm) }}" min="0" step="0.1">
                            </div>
                            <div>
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control"
                                    value="{{ old('sort_order', $property->sort_order) }}" min="0">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control">{{ old('description', $property->description) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Pricing --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-tag"></i></div>
                        <h3>Pricing</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="two-col">
                            <div>
                                <label class="form-label">Base Price / Night <span class="req">*</span></label>
                                <div class="input-prefix">
                                    <span>₱</span>
                                    <input type="number" name="base_price" class="form-control"
                                        value="{{ old('base_price', $property->base_price) }}" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Weekend Price / Night</label>
                                <div class="input-prefix">
                                    <span>₱</span>
                                    <input type="number" name="weekend_price" class="form-control"
                                        value="{{ old('weekend_price', $property->weekend_price) }}" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Amenities --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#dcfce7;color:#15803d;"><i class="bi bi-stars"></i></div>
                        <h3>Amenities</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="amenities-grid">
                            @php
                            $amenityList = ['WiFi','Air Conditioning','Private Pool','Hot Tub','Fully Equipped Kitchen','BBQ Grill','Parking','Garden View','Mountain View','Sea View','Smart TV','Refrigerator','Washing Machine','Safety Deposit Box','Balcony / Terrace','Karaoke','Game Room','Fire Pit','Outdoor Shower','Water Heater','Generator','CCTV','Rice Cooker','Dining Area'];
                            $selected = old('amenities', $property->amenities ?? []);
                            @endphp
                            @foreach($amenityList as $amenity)
                            <label class="amenity-check {{ in_array($amenity, $selected) ? 'checked' : '' }}">
                                <input type="checkbox" name="amenities[]" value="{{ $amenity }}" {{ in_array($amenity, $selected) ? 'checked' : '' }}>
                                <span class="check-icon">{{ in_array($amenity, $selected) ? '✓' : '' }}</span>
                                <span>{{ $amenity }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>

            {{-- RIGHT --}}
            <div>

                {{-- Current Images + Upload --}}
<div class="form-card">
    <div class="form-card-header">
        <div class="card-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="bi bi-images"></i></div>
        <h3>Property Photos</h3>
    </div>
    <div class="form-card-body">

        @if($property->images->count() > 0)
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                Current photos — click ✕ to remove
            </p>
            <div class="existing-images" id="existingImages">
                @foreach($property->images as $image)
                <div class="existing-img-item" data-image-id="{{ $image->id }}">
                    <img src="{{ asset('storage/'.$image->image_path) }}" alt="{{ $image->alt_text ?? $property->property_name }}">
                    @if($image->is_primary)
                        <span class="primary-badge">Primary</span>
                    @endif
                    <button type="button" 
                            class="delete-img-btn"
                            onclick="deleteImage(this)"
                            title="Remove this photo">
                        ✕
                    </button>
                </div>
                @endforeach
            </div>
            <hr style="margin:20px 0; border-color:var(--border);">
        @endif

        <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
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
                        <div class="card-icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-sliders"></i></div>
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

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Amenity checkboxes (keep your existing code)
document.querySelectorAll('.amenity-check').forEach(label => {
    label.addEventListener('click', () => {
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
function deleteImage(btn) {
    if (!confirm('Are you sure you want to permanently delete this photo?')) {
        return;
    }

    const imageContainer = btn.closest('.existing-img-item');
    const imageId = imageContainer.getAttribute('data-image-id');

    fetch(`/admin/properties/images/${imageId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (response.ok) {
            imageContainer.style.transition = 'all 0.3s ease';
            imageContainer.style.opacity = '0';
            imageContainer.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                imageContainer.remove();
            }, 300);
        } else {
            alert('Failed to delete the image. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the image.');
    });
}

</script>
@include('admin.partials.realtime') 
</body>
</html>