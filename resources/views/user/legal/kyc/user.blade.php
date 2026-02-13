@extends('layouts.app')

@section('title', 'User Identity Verification')

@section('content')

    <div class="card shadow-sm card-form p-4">

        <h4 class="mb-2 text-center">User Identity Verification</h4>

        <p class="text-muted text-center small mb-4">
            Your details are used only for identity verification and legal compliance.
            Documents are stored securely and never shared publicly.
        </p>

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


        @if (isset($kycStatus) && $kycStatus)
            <div class="text-center py-4">
                @if ($kycStatus->status === 'pending')
                    <h5 class="text-warning">Verification Under Review</h5>
                @elseif($kycStatus->status === 'approved')
                    <h5 class="text-success">Verification Approved</h5>
                @elseif($kycStatus->status === 'rejected')
                    <h5 class="text-danger">Verification Rejected</h5>
                @endif
            </div>
        @else
            <form id="kycForm" method="POST" action="{{ route('user.kyc.submit') }}" enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="user_id" value="{{ $user->id }}">

                {{-- ================= STEP 1 BASIC ================= --}}
                <div class="step-section" data-step="1">

                    <label class="form-label">Legal Full Name *</label>
                    <input class="form-control mb-3 required-field" name="legal_name" value="{{ old('legal_name') }}">

                    <label class="form-label">Date of Birth *</label>
                    <input type="date" id="dobInput" class="form-control mb-3 required-field" name="dob"
                        value="{{ old('dob') }}">

                </div>


                {{-- ================= STEP 2 AADHAAR ================= --}}
                <div class="step-section d-none" data-step="2">

                    <label>Aadhaar Number *</label>

                    <input id="aadhaarDisplay" class="form-control mb-3 required-field" maxlength="14"
                        placeholder="XXXX XXXX XXXX">

                    <input type="hidden" id="aadhaarReal" name="aadhaar_number" value="{{ old('aadhaar_number') }}">

                    <label>Aadhaar Front *</label>
                    <input type="file" accept="image/*" class="form-control mb-3 required-field"
                        name="aadhaar_front_image">

                    <label>Aadhaar Back *</label>
                    <input type="file" accept="image/*" class="form-control mb-3 required-field"
                        name="aadhaar_back_image">

                </div>


                {{-- ================= STEP 3 SELFIE ================= --}}
                <div class="step-section d-none" data-step="3">

                    <label>Live Selfie *</label>

                    <input type="file" id="selfieInput" accept="image/*" capture="user"
                        class="form-control mb-3 required-field" name="selfie_image">

                    <p class="small text-muted">
                        Camera opens automatically on supported devices.
                        If permission denied you can select from gallery.
                    </p>

                </div>


                {{-- ================= STEP 4 PAN ================= --}}
                <div class="step-section d-none" data-step="4">

                    <label>PAN Number (Optional)</label>
                    <input id="panInput" class="form-control mb-3" name="pan_card_number" maxlength="10"
                        value="{{ old('pan_card_number') }}" placeholder="ABCDE1234F">

                    <label>PAN Card Image</label>
                    <input type="file" accept="image/*" class="form-control mb-3" name="pan_card_image">

                </div>


                {{-- ================= NAVIGATION ================= --}}
                <div class="d-flex gap-2 mt-3">
                    <button type="button" id="prevBtn" class="btn btn-outline-secondary w-50 d-none">Back</button>
                    <button type="button" id="nextBtn" class="btn btn-success w-50">Next</button>
                    <button type="submit" id="submitBtn" class="btn btn-primary w-50 d-none">
                        Submit Verification
                    </button>
                </div>

            </form>
        @endif

    </div>

@endsection



@push('scripts')
    @if (!isset($kycStatus))
        <script>
            let currentStep = 1;
            const totalSteps = 4;

            const form = document.getElementById('kycForm');
            const nextBtn = document.getElementById('nextBtn');
            const prevBtn = document.getElementById('prevBtn');
            const submitBtn = document.getElementById('submitBtn');

            const dobInput = document.getElementById('dobInput');
            const aadhaarDisplay = document.getElementById('aadhaarDisplay');
            const aadhaarReal = document.getElementById('aadhaarReal');
            const panInput = document.getElementById('panInput');


            // =====================
            // PREVENT ENTER SUBMIT
            // =====================
            form.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });


            // =====================
            // SHOW STEP
            // =====================
            function showStep(step) {

                document.querySelectorAll('.step-section')
                    .forEach(el => el.classList.add('d-none'));

                document.querySelector('[data-step="' + step + '"]')
                    .classList.remove('d-none');

                prevBtn.classList.toggle('d-none', step === 1);
                nextBtn.classList.toggle('d-none', step === totalSteps);
                submitBtn.classList.toggle('d-none', step !== totalSteps);
            }


            // =====================
            // AGE CHECK
            // =====================
            function isAdult() {

                if (!dobInput.value) return false;

                const dob = new Date(dobInput.value);
                const today = new Date();

                let age = today.getFullYear() - dob.getFullYear();
                const m = today.getMonth() - dob.getMonth();

                if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;

                return age >= 18;
            }


            // =====================
            // AADHAAR FORMAT + INIT
            // =====================
            function formatAadhaar(v) {
                let digits = v.replace(/\D/g, '').substring(0, 12);
                let parts = digits.match(/.{1,4}/g);
                return parts ? parts.join(' ') : digits;
            }

            // restore display if old value exists
            if (aadhaarReal.value) {
                aadhaarDisplay.value = formatAadhaar(aadhaarReal.value);
            }

            aadhaarDisplay.addEventListener('input', function() {

                let digits = this.value.replace(/\D/g, '').substring(0, 12);
                aadhaarReal.value = digits;

                this.value = formatAadhaar(digits);
            });


            // =====================
            // PAN LIMIT
            // =====================
            panInput?.addEventListener('input', function() {
                this.value = this.value
                    .toUpperCase()
                    .replace(/[^A-Z0-9]/g, '')
                    .substring(0, 10);
            });


            // =====================
            // VALIDATION
            // =====================
            function validateStep(step) {

                let valid = true;

                const section = document.querySelector('[data-step="' + step + '"]');

                section.querySelectorAll('.required-field').forEach(f => {

                    if (f.type === 'file') {
                        if (!f.files.length) {
                            f.classList.add('is-invalid');
                            valid = false;
                        } else f.classList.remove('is-invalid');
                    } else if (!f.value.trim()) {
                        f.classList.add('is-invalid');
                        valid = false;
                    } else f.classList.remove('is-invalid');

                });

                if (step === 1 && !isAdult()) {
                    dobInput.classList.add('is-invalid');
                    alert('You must be 18 years or older.');
                    return false;
                }

                return valid;
            }


            // =====================
            // NAV BUTTONS
            // =====================
            nextBtn.onclick = () => {
                if (!validateStep(currentStep)) return;
                currentStep++;
                showStep(currentStep);
            }

            prevBtn.onclick = () => {
                currentStep--;
                showStep(currentStep);
            }


            // =====================
            // SUBMIT VALIDATION
            // =====================
            form.addEventListener('submit', e => {
                if (!validateStep(totalSteps)) e.preventDefault();
            });


            // ==================================================
            // 🔥 VERY IMPORTANT — RESTORE STEP AFTER ERROR
            // ==================================================

            function detectStepFromOldData() {

                if (aadhaarReal.value) {
                    currentStep = 2;
                }

                if (document.querySelector('[name="selfie_image"]').value) {
                    currentStep = 3;
                }

                if (panInput.value) {
                    currentStep = 4;
                }
            }

            // run once
            detectStepFromOldData();
            showStep(currentStep);
        </script>
    @endif
@endpush
