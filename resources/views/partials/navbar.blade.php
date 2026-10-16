<nav class="navbar admin-navbar navbar-expand bg-white">

    <div class="container-fluid px-3 px-lg-4">

        <button class="sidebar-toggle" type="button" data-sidebar-toggle>
            <span></span>
            <span></span>
            <span></span>
        </button>

        

        @php $navCompany = auth()->user()?->company; @endphp
        @if($navCompany)
            <span class="d-none d-md-inline-flex align-items-center gap-2 ms-3 small fw-semibold" style="color:var(--zp-ink-2)">
                <i class="bi {{ $navCompany->isDedicated() ? 'bi-buildings' : 'bi-cloud-check' }}" style="color:var(--zp-green)"></i>
                {{ $navCompany->name }}
                <span class="zp-portal-pill {{ $navCompany->isDedicated() ? '' : 'off' }}" title="{{ $navCompany->isDedicated() ? 'Dedicated workspace with its own database' : 'Shared workspace' }}">
                    {{ $navCompany->isDedicated() ? $navCompany->workspaceHost() : 'Shared' }}
                </span>
            </span>
        @endif

        <div class="navbar-actions ms-auto">

        @include('partials.translate', ['variant' => 'light'])

        {{-- Theme Toggle --}}
    
            <button class="icon-button" type="button" data-theme-toggle>
                <i class="bi bi-moon-stars " data-theme-icon></i>
            </button>

            <div class="dropdown" >

                <button class="profile-button dropdown-toggle" data-bs-toggle="dropdown">

                    @php
                    $names = explode(' ', auth()->user()->name ?? 'Guest');

                    $initials = strtoupper(
                    substr($names[0] ?? '', 0, 1) .
                    substr($names[1] ?? '', 0, 1)
                    );
                    @endphp

                    <div class="avatar-img avatar-sm rounded-circle d-flex align-items-center justify-content-center fw-semibold"
                        style="
                            width:36px;
                            height:36px;
                            background:linear-gradient(135deg,#00742D,#0A3B22);
                            color:#fff;
                            font-size:12px;
                            flex-shrink:0;
                            ">
                        {{ $initials }}
                    </div>

                   

                </button>

                <ul class="dropdown-menu dropdown-menu-end">

                    <li>
                        <a class="dropdown-item" href="{{ route('profile.index') }}">
                            Profile
                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item" href="{{ auth()->user()?->isCompanyAdmin() ? route('company.settings') : route('attendance.settings') }}">
                            Company Settings
                        </a>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>

                        <form action="{{ route('logout') }}" method="POST">

                            @csrf

                            <button class="dropdown-item">
                                Logout
                            </button>

                        </form>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</nav>