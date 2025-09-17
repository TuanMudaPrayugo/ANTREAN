@include('layoutuser/header')

    <body id="body">
        
        @include('layoutuser/leftbar')

        @include('layoutuser/topbar')

        <div class="page-wrapper">

            <!-- Page Content-->
            <div class="page-content-tab">

                <div class="container-fluid">
                    @yield('content')


                </div><!-- container -->
                    @yield('main_content')
                @include('layoutuser/footer')
                 
                
            </div>
            <!-- end page content -->
        </div>
        <!-- end page-wrapper -->

        <!-- Javascript  -->   
        <!-- vendor js -->
        
        <script src="{{asset('metrica/dist/assets/libs/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
        <script src="{{asset('metrica/dist/assets/libs/simplebar/simplebar.min.js')}}"></script>
        <script src="{{asset('metrica/dist/assets/libs/feather-icons/feather.min.js')}}"></script>
        <script src="{{asset('metrica/dist/assets/libs/mobius1-selectr/selectr.min.js')}}"></script>
        <script src="{{asset('metrica/dist/assets/libs/huebee/huebee.pkgd.min.js')}}"></script>
        <script src="{{asset('metrica/dist/assets/libs/vanillajs-datepicker/js/datepicker-full.min.js')}}"></script>
        <script src="{{asset('metrica/dist/assets/js/moment.js')}}"></script>
        <script src="{{asset('metrica/dist/assets/libs/imask/imask.min.js')}}"></script>

        <script src="{{asset('metrica/dist/assets/js/pages/forms-advanced.js')}}"></script>
        
    
        <!-- App js -->
        <script src="{{asset('metrica/dist/assets/js/app.js')}}"></script>

        <!-- Sweet-Alert  -->
        <script src="{{asset('metrica/dist/assets/libs/sweetalert2/sweetalert2.min.js')}}"></script>
        <script src="{{asset('metrica/dist/assets/js/pages/sweet-alert.init.js')}}"></script>

        {{-- Script tambahan dari halaman via @push('scripts') --}}
        @stack('scripts')


   @if (session('success'))
    <script>
    document.addEventListener('DOMContentLoaded', function () {
    const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',   // atau 'topRight' sesuai selera
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
     });
    Toast.fire({
    icon: 'success',
    title: @json(session('success'))
    });
    });
    </script>
    @endif

    @if (session('error'))
    <script>
    document.addEventListener('DOMContentLoaded', function () {
    const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',   // atau 'topRight' sesuai selera
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
     });
    Toast.fire({
    icon: 'error',
    title: @json(session('error'))
    });
    });
    </script>
    @endif

    
    </body>
    <!--end body-->
</html>