<!DOCTYPE html>
<html lang="en">

<head>
    @include('partials.head')
</head>

<body
  data-user-name="{{ auth()->user()->name }}"
  data-user-avatar="{{ asset('assets/images/avatar/avatar.jpg') }}">

  <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<div class="admin-shell">

  

    <div class="sidebar-backdrop" data-sidebar-close></div>

    @include('partials.sidebar')

    <div class="admin-main">

        @include('partials.navbar')

        <main class="dashboard-content page-transition" id="page-content">

    
            <div class="container-fluid px-3 px-lg-4 py-4">

                @include('partials.module-tabs')

                @include('partials.alerts')

                @if(request()->routeIs('dashboard'))
                    @include('partials.profile-completion')
                @endif

                @yield('content')

            </div>

        </main>

        @include('partials.footer')

    </div>

</div>

@include('partials.scripts')

<script>
    // Show loader manually
    function showLoader() {
        document.getElementById('global-loader').classList.remove('d-none');
    }

    // Hide loader
    function hideLoader() {
        document.getElementById('global-loader').classList.add('d-none');
    }

    // Show loader on any form submit
    document.addEventListener('submit', function () {
        showLoader();
    });

    // Show loader on page navigation clicks
    // document.addEventListener('click', function (e) {
    //     const link = e.target.closest('a');
    //     if (link && link.href && !link.target) {
    //         showLoader();
    //     }
    // });

    

    document.addEventListener("click", function (e) {
    const link = e.target.closest("a");

    if (link && link.href && !link.target && !link.hasAttribute("download")) {
        e.preventDefault();

        document.getElementById("page-content").style.opacity = "0";
        document.getElementById("page-content").style.transform = "translateY(6px)";

        setTimeout(() => {
            window.location = link.href;
        }, 150);
    }
});

function showToast(message, type = 'success') {

    const colors = {
        success: 'bg-success',
        error: 'bg-danger',
        warning: 'bg-warning',
        info: 'bg-primary'
    };

    const toast = document.createElement('div');

    toast.className = `toast align-items-center text-white ${colors[type]} border-0 show mb-2`;
    toast.role = 'alert';

    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;

    document.getElementById('toast-container').appendChild(toast);

    // auto remove after 3 seconds
    setTimeout(() => {
        toast.remove();
    }, 3000);
}



</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const selector = document.getElementById('employeeSelector');
    const container = document.getElementById('employeePayrollSummary');

    if (!selector || !container) return; // only present on the payroll run page

    selector.addEventListener('change', function () {

        const payrollId = this.value;

        if (!payrollId) return;

        const url = `/payroll/runs/${runId}/employee/${payrollId}/summary`;

        container.innerHTML = `
            <div class="text-center py-3">
                Loading payroll...
            </div>
        `;

        fetch(url)
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;
            })
            .catch(() => {
                container.innerHTML = `
                    <div class="text-danger text-center">
                        Failed to load payroll
                    </div>
                `;
            });

    });

});
</script>






<div id="global-loader" class="global-loader d-none">
    <div class="loader-box">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 mb-0">Loading...</p>
    </div>
</div>


@stack('modals')

<script src="{{ asset('assets/js/zp-confetti.js') }}"></script>
@include('partials.ai-mode')
@if(session('celebrate'))
    <script>window.addEventListener('load', () => ZPConfetti.burst());</script>
@endif



</body>
</html>