@extends('layouts.app')

@section('title', 'Vehicle KYC')

@section('content')

    <div class="card shadow-sm card-form p-4">

        <h4 class="mb-4 text-center">Vehicle KYC</h4>

        {{-- ===== SUCCESS / ERROR ===== --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif


        {{-- ================= STATUS VIEW ================= --}}
        @if (isset($vehicleKycStatus) && $vehicleKycStatus)
            <div class="text-center py-4">

                @if ($vehicleKycStatus->status === 'pending')
                    <h5 class="text-warning">Vehicle KYC Under Review</h5>
                    <p>Your submission is currently being reviewed.</p>
                @elseif($vehicleKycStatus->status === 'approved')
                    <h5 class="text-success">Vehicle KYC Approved</h5>
                    <p>Your vehicle is verified successfully.</p>
                @elseif($vehicleKycStatus->status === 'rejected')
                    <h5 class="text-danger">Vehicle KYC Rejected</h5>
                    <p>Please submit again with correct information.</p>
                @endif

            </div>
        @else
            <form id="kycForm" method="POST" action="{{ route('vehicle.kyc.submit') }}" enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="user_id" value="{{ $user->id }}">

                {{-- ================= STEP 1 ================= --}}
                <div class="step-section" data-step="1">

                    <h6 class="mb-3">Vehicle Basic *</h6>

                    <label class="form-label">Vehicle Type *</label>
                    <select class="form-select mb-3 required-field" name="mst_vehicle_id">
                        <option value="">Select Vehicle</option>
                        @foreach ($mstVehicles as $vehicle)
                            <option value="{{ $vehicle->id }}"
                                {{ old('mst_vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->vehicle_name }}
                            </option>
                        @endforeach
                    </select>

                    <input class="form-control mb-3 required-field" name="license_plate_number"
                        value="{{ old('license_plate_number') }}" placeholder="License Plate Number *">

                    <input class="form-control mb-3 required-field" name="vehicle_maker" value="{{ old('vehicle_maker') }}"
                        placeholder="Vehicle Maker (Tata / Eicher / Mahindra / Ashok Leyland) *">

                    <input class="form-control mb-3 required-field" name="vehicle_color" value="{{ old('vehicle_color') }}"
                        placeholder="Vehicle Color *">

                </div>

                {{-- ================= STEP 2 ================= --}}
                <div class="step-section d-none" data-step="2">

                    <h6 class="mb-3">Driving License *</h6>

                    <input class="form-control mb-3 required-field" name="driving_license_number"
                        value="{{ old('driving_license_number') }}" placeholder="Driving License Number *">

                    <label class="form-label">Driving License Photo *</label>
                    <input type="file" class="form-control mb-3 required-field" name="driving_license_image">

                </div>

                {{-- ================= STEP 3 ================= --}}
                <div class="step-section d-none" data-step="3">

                    <h6 class="mb-3">Vehicle Images *</h6>


                    <label class="form-label">Vehicle With Driver *</label>
                    <input type="file" class="form-control mb-3 required-field" name="vehicle_with_driver_image">

                    <label class="form-label">Vehicle Cargo Area *</label>
                    <input type="file" class="form-control mb-3 required-field" name="vehicle_cargo_image">

                    <label class="form-label">Vehicle Front *</label>
                    <input type="file" class="form-control mb-3 required-field" name="vehicle_front_image">

                    <label class="form-label">Vehicle Back *</label>
                    <input type="file" class="form-control mb-3 required-field" name="vehicle_back_image">

                    <label class="form-label">Vehicle Left</label>
                    <input type="file" class="form-control mb-3" name="vehicle_left_image">

                    <label class="form-label">Vehicle Right</label>
                    <input type="file" class="form-control mb-3" name="vehicle_right_image">




                </div>

                {{-- ================= STEP 4 ================= --}}
                <div class="step-section d-none" data-step="4">

                    <h6 class="mb-3">Registration (RC Book) *</h6>

                    <input class="form-control mb-3 required-field" name="registration_number"
                        value="{{ old('registration_number') }}" placeholder="Registration Number *">

                    <label class="form-label">RC Book Photo *</label>
                    <input type="file" class="form-control mb-3 required-field" name="rc_book_image">

                </div>

                {{-- ================= STEP 5 ================= --}}
                <div class="step-section d-none" data-step="5">

                    <h6 class="mb-3">Insurance *</h6>

                    <input class="form-control mb-3 required-field" name="insurance_policy_number"
                        value="{{ old('insurance_policy_number') }}" placeholder="Insurance Policy Number *">

                    <label class="form-label">Insurance Photo *</label>
                    <input type="file" class="form-control mb-3 required-field" name="insurance_image">

                </div>

                {{-- ================= NAVIGATION ================= --}}
                <div class="d-flex gap-2 mt-3">
                    <button type="button" id="prevBtn" class="btn btn-outline-secondary w-50 d-none">Back</button>
                    <button type="button" id="nextBtn" class="btn btn-success w-50">Next</button>
                    <button type="submit" id="submitBtn" class="btn btn-primary w-50 d-none">Submit Vehicle KYC</button>
                </div>

            </form>
        @endif

    </div>

@endsection


@push('scripts')
    @if (!isset($vehicleKycStatus))
        <script>
            let currentStep = 1;
            const totalSteps = 5;

            const nextBtn = document.getElementById('nextBtn');
            const prevBtn = document.getElementById('prevBtn');
            const submitBtn = document.getElementById('submitBtn');

            function showStep(step) {
                document.querySelectorAll('.step-section').forEach(el => el.classList.add('d-none'));
                document.querySelector('[data-step="' + step + '"]').classList.remove('d-none');
                prevBtn.classList.toggle('d-none', step === 1);
                nextBtn.classList.toggle('d-none', step === totalSteps);
                submitBtn.classList.toggle('d-none', step !== totalSteps);
            }

            function validateStep(step) {

                let valid = true;
                const section = document.querySelector('[data-step="' + step + '"]');
                const fields = section.querySelectorAll('.required-field');

                fields.forEach(field => {

                    if (field.tagName === 'SELECT') {
                        if (!field.value) {
                            field.classList.add('is-invalid');
                            valid = false;
                        } else field.classList.remove('is-invalid');
                        return;
                    }

                    if (field.type === 'file') {
                        if (!field.files.length) {
                            field.classList.add('is-invalid');
                            valid = false;
                        } else field.classList.remove('is-invalid');
                        return;
                    }

                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        valid = false;
                    } else field.classList.remove('is-invalid');

                });

                return valid;
            }

            nextBtn.addEventListener('click', () => {
                if (!validateStep(currentStep)) return;
                if (currentStep < totalSteps) {
                    currentStep++;
                    showStep(currentStep);
                }
            });

            prevBtn.addEventListener('click', () => {
                if (currentStep > 1) {
                    currentStep--;
                    showStep(currentStep);
                }
            });

            document.getElementById('kycForm').addEventListener('submit', function(e) {
                if (!validateStep(totalSteps)) {
                    e.preventDefault();
                }
            });
        </script>
    @endif
@endpush
