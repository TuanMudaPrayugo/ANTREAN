<!-- leftbar-tab-menu -->
        <div class="leftbar-tab-menu">
            <div class="main-icon-menu">
                <a href="index.html" class="logo logo-metrica d-block text-center">
                    <span>
                        <img src="{{asset('metrica/dist/assets/images/logo-sahar-sm.png')}}" alt="logo-large" class="logo-sm" style="height:50px; width:auto;">
                    </span>
                </a>
                <div class="main-icon-menu-body">
                    <div class="position-reletive h-100" data-simplebar style="overflow-x: hidden;">
                        <ul class="nav nav-tabs" role="tablist" id="tab-menu">
                            <li class="nav-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard" data-bs-trigger="hover">
                                <a href="#MetricaDashboard" id="dashboard-tab" class="nav-link">
                                    <i class="ti ti-smart-home menu-icon"></i>
                                </a><!--end nav-link-->
                            </li><!--end nav-item-->

                            <li class="nav-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Wajib Pajak" data-bs-trigger="hover">
                                <a href="#MetricaWP" id="WajibPajak-tab" class="nav-link">
                                    <i class="ti ti-smart-home menu-icon"></i>
                                </a><!--end nav-link-->
                            </li><!--end nav-item-->

                            <li class="nav-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Petugas" 
                            data-bs-trigger="hover">
                                <a href="#Metricapetugas" id="petugas-tab" class="nav-link">
                                    <i class="ti ti-smart-home menu-icon"></i>
                                </a><!--end nav-link-->
                            </li><!--end nav-item-->
                            

                            <li class="nav-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Setting" data-bs-trigger="hover">
                                <a href="#MetricaSetting" id="setting-tab" class="nav-link">
                                    <i class="mdi mdi-settings menu-icon"></i>
                                </a><!--end nav-link-->
                            </li><!--end nav-item-->
                            
                        </ul><!--end nav-->
                    </div><!--end /div-->
                </div><!--end main-icon-menu-body-->
                {{-- <div class="pro-metrica-end">
                    <a href="" class="profile">
                        <img src="{{asset('metrica/dist/assets/images/users/user-4.jpg')}}" alt="profile-user" class="rounded-circle thumb-sm">
                    </a>
                </div><!--end pro-metrica-end--> --}}
            </div>
            <!--end main-icon-menu-->

            <div class="main-menu-inner">
                <!-- LOGO -->
                <div class="topbar-left">
                    <a href="index.html" class="logo">
                        <span>
                            <img src="{{asset('metrica/dist/assets/images/logo_si_sahar_small.png')}}" alt="logo-large" class="logo-lg logo-dark" style="height:30px; width:auto;">
                            <img src="{{asset('metrica/dist/assets/images/logo_si_sahar_small')}}" alt="logo-large" class="logo-lg logo-light" style="height:30px; width:auto;">
                        </span>
                    </a><!--end logo-->
                </div><!--end topbar-left-->
                <!--end logo-->
                <div class="menu-body navbar-vertical tab-content" data-simplebar>
                    <div id="MetricaDashboard" class="main-icon-menu-pane tab-pane" role="tabpanel"
                        aria-labelledby="dasboard-tab">
                        <div class="title-box">
                            <h6 class="menu-title">Dashboard</h6>
                        </div>

                        <ul class="nav flex-column">
                            <li class="nav-item {{$menuDashboard ?? ''}}">
                                <a class="nav-link" href="{{route('dashboard')}}">Dashboard Antrean</a>
                            </li><!--end nav-item-->
                            
                        </ul><!--end nav-->
                    </div><!-- end Dashboards -->

                     
                    <div id="Metricapetugas" class="main-icon-menu-pane tab-pane" role="tabpanel"
                        aria-labelledby="petugas-tab">
                        <div class="title-box">
                            <h6 class="menu-title">Akses Petugas</h6>
                        </div>

                        <ul class="nav flex-column">
                            <li class="nav-item {{$menuKonfirmasiPetugas ?? ''}}">
                                <a class="nav-link" href="{{route('petugas.index')}}">List Antrean</a>
                            </li><!--end nav-item-->
                            
                        </ul><!--end nav-->
                    </div><!-- end Dashboards -->

                    <div id="MetricaWP" class="main-icon-menu-pane tab-pane" role="tabpanel"
                        aria-labelledby="WajibPajak-tab">
                        <div class="title-box">
                            <h6 class="menu-title">Area Wajib Pajak</h6>
                        </div>

                        <ul class="nav flex-column">
                            <li class="nav-item {{$menuAntrean ?? ''}}">
                                <a class="nav-link" href="{{route('Antrean')}}">Antrean</a>
                            </li><!--end nav-item-->

                            <li class="nav-item {{$menuTanyaSahar ?? ''}}">
                                <a class="nav-link" href="{{route('TanyaSahar')}}">Tanya Sahar</a>
                            </li><!--end nav-item-->

                            
                            
                        </ul><!--end nav-->
                    </div><!-- end Dashboards -->

                    

                    <div id="MetricaSetting" class="main-icon-menu-pane tab-pane" role="tabpanel" aria-labelledby="pages-tab">
                        <div class="title-box">
                            <h6 class="menu-title">Master Setting</h6>
                        </div>
                        <ul class="nav flex-column">
                            
                            <li class="nav-item {{$menuKLayanan ?? ''}}">
                                <a class="nav-link" href="{{route('KLayanan')}}">Kelola Layanan</a>
                            </li><!--end nav-item-->
                            <li class="nav-item">
                                <a class="nav-link" href="{{route('KStep')}}">Kelola Step dan Issue</a>
                            </li><!--end nav-item-->
                            <li class="nav-item">
                                <a class="nav-link" href="{{route('KategoriIssue')}}">Kelola Kategori Issue</a>
                            </li><!--end nav-item-->
                            <li class="nav-item">
                                <a class="nav-link" href="{{route('SyaratDokumen')}}">Syarat Dokumen</a>
                            </li><!--end nav-item-->
                            
                            
                        </ul><!--end nav-->
                    </div><!-- end Pages -->
                    
                </div>
                <!--end menu-body-->
            </div><!-- end main-menu-inner-->
        </div>
        <!-- end leftbar-tab-menu-->