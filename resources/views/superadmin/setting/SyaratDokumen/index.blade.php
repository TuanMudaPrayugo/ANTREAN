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
                    <div class="col-12">

                        <div class="d-flex justify-content-between mb-3">
                                <div class="align-self-center">
                                    <a href="{{route('SyaratDokumenCreate')}}" class="btn btn-de-primary btn-sm">
                                    <i data-feather="plus" class="align-self-center icon-xs me-2"></i>Tambah Syarat Dokumen</a>
                                    
                                </div><!--end /div-->
                            </div><!--end /div-->



                        <div class="card">    
                        <div class="card-body"> 
                            
                            
                        <div class="row text-center">

                            @foreach ($grouped as $serviceName => $docs)
                            @php $slug = Str::slug($serviceName); @endphp

                        <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">{{ $serviceName }}</h4>
                            </div><!--end card-header-->
                            <div class="card-body">
                                <div class="accordion" id="accordionExample-faq">
                                    <div class="accordion-item">
                                        <h5 class="accordion-header m-0" id="headingOne">
                                            <button 
                                            class="accordion-button collapsed" 
                                            type="button" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#collapse-{{ $slug }}" 
                                            aria-expanded="true" 
                                            aria-controls="collapse-{{ $slug }}"">
                                                Syarat Dokumen
                                            </button>
                                        </h5>
                                        <div id="collapse-{{ $slug }}"
                                            class="accordion-collapse collapse"  {{-- tanpa "show" --}}
                                            aria-labelledby="heading-{{ $slug }}"
                                            data-bs-parent="#layananAccordion">
                                            <div class="accordion-body">
                                                @foreach ($docs as $doc)
                                             <div class="alert alert-info border-0 mb-2 py-2" role="alert">
                                            <div class="d-flex align-items-center w-100 gap-2">
                                                <div class="flex-grow-1 text-start">
                                                {{ $doc->require_document }}
                                                </div>

                                                <div class="d-flex align-items-center gap-3">
                                                <a href="{{route('SyaratDokumenEdit',$doc->id)}}" class="text-warning text-decoration-none">
                                                    <i data-feather="edit-2" class="me-1" style="width:16px;height:16px;"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger float-end ms-2" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#ModalDeleteIssue" 
                                                >
                                                    <i data-feather="trash-2" class="me-1" style="width:16px;height:16px;"></i> Hapus
                                            </button>
                                               
                                            </div>
                                                
                                            </div>
                                            </div>
                                            @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div> <!--end eccording-->
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div><!--end col-->
                                                                                                            
                                                            
                            @endforeach
                            </div> <!--end row text-center-->
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div> <!-- end col -->        
                </div> <!-- end row -->
                
                    @endsection