                    @extends('layoutuser/app')                             
                    
                    <!-- Page-Title -->
                    @section('content')
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="page-title-box">
                                <div class="float-end">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="#">Metrica</a>
                                        </li><!--end nav-item-->
                                        <li class="breadcrumb-item"><a href="#">Pages</a>
                                        </li><!--end nav-item-->
                                        <li class="breadcrumb-item active">Starter</li>
                                    </ol>
                                </div>
                                <h4 class="page-title">{{$title}}</h4>
                            </div><!--end page-title-box-->
                        </div><!--end col-->
                    </div>
                    <!-- end page title end breadcrumb -->
                    @endsection

                    @section('main_content')
                       <div class="row">
                        @foreach ($grouped as $serviceName => $docs)
                            @php 
                             // ambil id layanan dari item pertama di grup
                            $layananId = optional($docs->first()->layanan)->id; 
                            @endphp
                    <div class="col-lg-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="pricingTable1 text-center">
                                    <img src="{{asset('metrica/dist/assets/images/icon/paper_2.jpg')}}" alt="" class="" height="120">
                                    <h6 class="title1 py-3 mt-2 mb-0">{{ $serviceName }}</h6>
                                    <ul class="list-unstyled pricing-content-2">
                                        <div class="alert alert-info border-0" role="alert">
                                    Perhatikan Syarat dokumen berikut, pastikan dokumen yang di perlukan sudah anda bawa semua sbeleum memulai proses pada layana ini.
                                </div>
                                        @foreach ($docs as $doc)
                                        <li>{{ $doc->require_document }}</li>
                                        
                                        @endforeach
                                    </ul>
                                    <hr class="hr-dashed my-4">
                                    <div class="alert alert-danger border-0" role="alert">
                                    <strong>Perhatian : Jika syarat dokumen sudah anda bawa</strong> Silhkan klik tombol mulai, untuk memulai proses antrean.
                                </div>
                                    <div class="d-grid">
                                         @if($layananId)
                                            <a href="{{ route('scan.show', ['layanan' => $layananId]) }}"
                                                class="btn btn-primary w-100">Mulai</a>
                                        @else
                                            <button class="btn btn-secondary w-100" disabled>Data layanan tidak tersedia</button>
                                        @endif
                                    </div>
                                    
                                </div><!--end pricingTable-->
                            </div><!--end card-body-->
                        </div> <!--end card-->                                   
                    </div><!--end col-->

                    

                    

                    @endforeach
                </div><!--end row-->
                    @endsection