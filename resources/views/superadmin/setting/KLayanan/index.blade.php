                    @extends('layout/app')                             
                    
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
                        <div class="col-lg-12">
                            <div class="d-flex justify-content-between mb-3">
                                <div class="align-self-center">
                                    <a href="{{route('KLayananCreate')}}" class="btn btn-de-primary btn-sm">
                                    <i data-feather="plus" class="align-self-center icon-xs me-2"></i>Tambah Data</a>  
                                </div><!--end /div-->
                                
                            </div><!--end /div-->
                        </div><!--end col-->
                    </div><!--end row-->
                    <div class="row">
                        @foreach ($KLayanan as $item)
                        <div class="col-lg-4">
                            <div class="card ">  
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col">                      
                                            <div class="media">
                                                <i data-feather="file-text" class="align-self-center icon-xs me-2"></i>
                                                <div class="media-body align-self-center">                                                                                                                       
                                                    <h5 class="m-0 font-15">{{$item->service_name}}</h5>
                                                    <p class="text-muted fw-semibold mb-0">Standart durasi : {{$item->nama_std_estimation_time}}</p>
                                                </div><!--end media-body-->
                                            </div><!--end media-->                      
                                        </div><!--end col-->
                                        <div class="col-auto"> 
                                            @if ($item->status_layanan==true)
                                            <span class="badge bg-soft-success">
                                                Aktif
                                            </span>
                                                @else
                                                <span class="badge bg-soft-danger">
                                                Tidak Aktif
                                            </span>
                                            @endif          
                                        </div><!--end col-->
                                    </div>  <!--end row-->                                  
                                </div><!--end card-header-->                                  
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">                                                    
                                            <p class="mb-0 ">{{$item->keterangan}}</p>
                                            <p class="mb-0 text-muted">tahapan layanan : </p>
                                            
                                        </div><!--end col-->
                                        
                                    </div><!--end row-->
                                    <button  class="btn btn-sm btn-outline-danger float-end ms-2" data-bs-toggle="modal" data-bs-target="#exampleModalDanger{{$item->id}}">Hapus</button>
                                    <a href="{{route('KLayananEdit',$item->id)}}" class="btn btn-sm btn-outline-warning float-end">Edit</a>
                                     @include('superadmin/setting/KLayanan/modaldelete')                                                                     
                                </div><!--end card-body-->
                            </div><!--end card-->
                        </div><!--end col-->
                        @endforeach                        
                    </div><!--end row-->
                    @endsection