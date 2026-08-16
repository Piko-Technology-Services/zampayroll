<aside class="admin-sidebar" id="adminSidebar">

    {{-- BRAND --}}
    <div class="sidebar-header">
        <a class="brand-mark" href="{{ route('dashboard') }}">
            <img src="http://misc.zampayroll.com/logo.png" width="36" height="36" alt="ZamPayroll Logo" class="brand-logo">
            <span class="brand-copy">
                <span class="brand-title">ZamPayroll</span>
                <span class="brand-subtitle">Payroll Made Simple</span>
            </span>
        </a>
    </div>

    {{-- NAVIGATION --}}
    <nav class="sidebar-nav">

        {{-- DASHBOARD --}}
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
           href="{{ route('dashboard') }}">
            <i class="bi bi-speedometer2 nav-icon"></i>
            <span class="nav-text">Dashboard</span>
        </a>

        {{-- EMPLOYEES --}}
        <div class="nav-group {{ request()->routeIs('employees.*') ? 'open' : '' }}">
            <a class="nav-link nav-group-toggle {{ request()->routeIs('employees.*') ? 'active' : '' }}"
               href="#" data-bs-toggle="collapse" data-bs-target="#navEmployees"
               aria-expanded="{{ request()->routeIs('employees.*') ? 'true' : 'false' }}">
                <i class="bi bi-people nav-icon"></i>
                <span class="nav-text">Employees</span>
                <i class="bi bi-chevron-down nav-caret"></i>
            </a>
            <div class="collapse nav-submenu {{ request()->routeIs('employees.*') ? 'show' : '' }}" id="navEmployees">
                <a class="nav-sublink {{ request()->routeIs('employees.index') ? 'active' : '' }}"
                   href="{{ route('employees.index') }}">
                    <i class="bi bi-list-ul"></i> All Employees
                </a>
                <a class="nav-sublink {{ request()->routeIs('employees.create') ? 'active' : '' }}"
                   href="{{ route('employees.create') }}">
                    <i class="bi bi-person-plus"></i> Add Employee
                </a>
                <a class="nav-sublink" href="{{ route('employees.sample-csv') }}">
                    <i class="bi bi-file-earmark-arrow-down"></i> Import Template
                </a>
            </div>
        </div>

        {{-- LEAVE --}}
        <div class="nav-group {{ request()->routeIs('leave.*') ? 'open' : '' }}">
            <a class="nav-link nav-group-toggle {{ request()->routeIs('leave.*') ? 'active' : '' }}"
               href="#" data-bs-toggle="collapse" data-bs-target="#navLeave"
               aria-expanded="{{ request()->routeIs('leave.*') ? 'true' : 'false' }}">
                <i class="bi bi-calendar-check nav-icon"></i>
                <span class="nav-text">Leave</span>
                <i class="bi bi-chevron-down nav-caret"></i>
            </a>
            <div class="collapse nav-submenu {{ request()->routeIs('leave.*') ? 'show' : '' }}" id="navLeave">
                <a class="nav-sublink {{ request()->routeIs('leave.years') || request()->routeIs('leave.sheet') ? 'active' : '' }}"
                   href="{{ route('leave.years') }}">
                    <i class="bi bi-table"></i> Leave Sheet
                </a>
                <a class="nav-sublink {{ request()->routeIs('leave.dashboard') ? 'active' : '' }}"
                   href="{{ route('leave.dashboard') }}">
                    <i class="bi bi-inbox"></i> Leave Requests
                </a>
            </div>
        </div>

        {{-- ATTENDANCE --}}
        <div class="nav-group {{ request()->routeIs('attendance.*') ? 'open' : '' }}">
            <a class="nav-link nav-group-toggle {{ request()->routeIs('attendance.*') ? 'active' : '' }}"
               href="#" data-bs-toggle="collapse" data-bs-target="#navAttendance"
               aria-expanded="{{ request()->routeIs('attendance.*') ? 'true' : 'false' }}">
                <i class="bi bi-clock-history nav-icon"></i>
                <span class="nav-text">Attendance</span>
                <i class="bi bi-chevron-down nav-caret"></i>
            </a>
            <div class="collapse nav-submenu {{ request()->routeIs('attendance.*') ? 'show' : '' }}" id="navAttendance">
                <a class="nav-sublink {{ request()->routeIs('attendance.calendar') || request()->routeIs('attendance.day') ? 'active' : '' }}"
                   href="{{ route('attendance.calendar') }}">
                    <i class="bi bi-calendar3"></i> Calendar
                </a>
                <a class="nav-sublink {{ request()->routeIs('attendance.report') ? 'active' : '' }}"
                   href="{{ route('attendance.report') }}">
                    <i class="bi bi-graph-up"></i> Attendance Report
                </a>
                <a class="nav-sublink {{ request()->routeIs('attendance.settings') ? 'active' : '' }}"
                   href="{{ route('attendance.settings') }}">
                    <i class="bi bi-sliders"></i> Working Days &amp; Holidays
                </a>
            </div>
        </div>

        {{-- OVERTIME --}}
        <div class="nav-group {{ request()->routeIs('overtime.*') ? 'open' : '' }}">
            <a class="nav-link nav-group-toggle {{ request()->routeIs('overtime.*') ? 'active' : '' }}"
               href="#" data-bs-toggle="collapse" data-bs-target="#navOvertime"
               aria-expanded="{{ request()->routeIs('overtime.*') ? 'true' : 'false' }}">
                <i class="bi bi-alarm nav-icon"></i>
                <span class="nav-text">Overtime</span>
                <i class="bi bi-chevron-down nav-caret"></i>
            </a>
            <div class="collapse nav-submenu {{ request()->routeIs('overtime.*') ? 'show' : '' }}" id="navOvertime">
                <a class="nav-sublink {{ request()->routeIs('overtime.dashboard') ? 'active' : '' }}"
                   href="{{ route('overtime.dashboard') }}">
                    <i class="bi bi-inbox"></i> Overtime Requests
                </a>
            </div>
        </div>

        {{-- LOANS --}}
        <div class="nav-group {{ request()->routeIs('loan.*') ? 'open' : '' }}">
            <a class="nav-link nav-group-toggle {{ request()->routeIs('loan.*') ? 'active' : '' }}"
               href="#" data-bs-toggle="collapse" data-bs-target="#navLoans"
               aria-expanded="{{ request()->routeIs('loan.*') ? 'true' : 'false' }}">
                <i class="bi bi-cash-coin nav-icon"></i>
                <span class="nav-text">Loans</span>
                <i class="bi bi-chevron-down nav-caret"></i>
            </a>
            <div class="collapse nav-submenu {{ request()->routeIs('loan.*') ? 'show' : '' }}" id="navLoans">
                <a class="nav-sublink {{ request()->routeIs('loan.dashboard') ? 'active' : '' }}"
                   href="{{ route('loan.dashboard') }}">
                    <i class="bi bi-inbox"></i> Loan Requests
                </a>
            </div>
        </div>

        {{-- PAYROLL --}}
        <div class="nav-group {{ request()->routeIs('payroll.*') ? 'open' : '' }}">
            <a class="nav-link nav-group-toggle {{ request()->routeIs('payroll.*') ? 'active' : '' }}"
               href="#" data-bs-toggle="collapse" data-bs-target="#navPayroll"
               aria-expanded="{{ request()->routeIs('payroll.*') ? 'true' : 'false' }}">
                <i class="bi bi-cash-stack nav-icon"></i>
                <span class="nav-text">Payroll</span>
                <i class="bi bi-chevron-down nav-caret"></i>
            </a>
            <div class="collapse nav-submenu {{ request()->routeIs('payroll.*') ? 'show' : '' }}" id="navPayroll">
                
                <a class="nav-sublink {{ request()->routeIs('payroll.runs.*') ? 'active' : '' }}"
                   href="{{ route('payroll.runs.index') }}">
                    <i class="bi bi-play-circle"></i> Payroll Runs
                </a>
               
                <a class="nav-sublink {{ request()->routeIs('payroll.reports.*') ? 'active' : '' }}"
                   href="{{ route('payroll.reports.index') }}">
                    <i class="bi bi-file-earmark-bar-graph"></i> Payroll Reports
                </a>
            </div>
        </div>

        {{-- REPORTS (general) --}}
        <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
           href="{{ route('reports.index') }}">
            <i class="bi bi-bar-chart nav-icon"></i>
            <span class="nav-text">Reports</span>
        </a>

        {{-- PUBLIC APPLICATION FORMS --}}
        <div class="nav-group">
            <a class="nav-link nav-group-toggle"
               href="#" data-bs-toggle="collapse" data-bs-target="#navPublicForms"
               aria-expanded="false">
                <i class="bi bi-box-arrow-up-right nav-icon"></i>
                <span class="nav-text">Public Forms</span>
                <i class="bi bi-chevron-down nav-caret"></i>
            </a>
            <div class="collapse nav-submenu" id="navPublicForms">
                <a class="nav-sublink nav-link-public" href="{{ route('leave.apply.form') }}" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> Apply for Leave
                </a>
                <a class="nav-sublink nav-link-public" href="{{ route('overtime.apply.form') }}" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> Apply for Overtime
                </a>
                <a class="nav-sublink nav-link-public" href="{{ route('loan.apply.form') }}" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> Apply for Loan
                </a>
            </div>
        </div>

        {{-- SETTINGS (company admin only) --}}
        @if(auth()->user()?->isCompanyAdmin())
        <div class="nav-group {{ request()->routeIs('settings.*') || request()->routeIs('profile.*') ? 'open' : '' }}">
            <a class="nav-link nav-group-toggle {{ request()->routeIs('settings.*') ? 'active' : '' }}"
               href="#" data-bs-toggle="collapse" data-bs-target="#navSettings"
               aria-expanded="{{ request()->routeIs('settings.*') ? 'true' : 'false' }}">
                <i class="bi bi-gear nav-icon"></i>
                <span class="nav-text">Settings</span>
                <i class="bi bi-chevron-down nav-caret"></i>
            </a>
            <div class="collapse nav-submenu {{ request()->routeIs('settings.*') ? 'show' : '' }}" id="navSettings">
                <a class="nav-sublink {{ request()->routeIs('settings.index') ? 'active' : '' }}"
                   href="{{ route('settings.index') }}">
                    <i class="bi bi-building"></i> Company Settings
                </a>
                <a class="nav-sublink {{ request()->routeIs('profile.index') || request()->routeIs('profile.edit') ? 'active' : '' }}"
                   href="{{ route('profile.index') }}">
                    <i class="bi bi-person-circle"></i> My Profile
                </a>
            </div>
        </div>
        @else
        <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}"
           href="{{ route('profile.index') }}">
            <i class="bi bi-person-circle nav-icon"></i>
            <span class="nav-text">My Profile</span>
        </a>
        @endif

    </nav>

</aside>