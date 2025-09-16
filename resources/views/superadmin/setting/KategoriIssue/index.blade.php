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
                                    <a href="{{route('KategoriIssueCreate')}}" class="btn btn-de-primary btn-sm">
                                    <i data-feather="plus" class="align-self-center icon-xs me-2"></i>Tambah Data Kategori</a>
                                    
                                    
                                </div><!--end /div-->
                            </div><!--end /div-->

                        <div class="card">
                            <div class="card-header bg-blue">
                                <h4 class="card-title text-white"></h4>
                                <p class="text-muted mb-0"></p>
                            </div><!--end card-header-->
                            <div class="card-body">        
                                <div class="row text-center">
                                    <div class="col-lg-6"><span class="border py-2 bg-light d-block mb-2 mb-lg-0">Nama Kategori</span>
                                        <ul class="list-group">
                                            @foreach ($KategoriIssue as $item)
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                
                                                <div>
                                                    <i class="la la-check text-muted font-16 me-2"></i>
                                                    {{$item->category_name}}
                                                </div>
                                                
                                                   <div class="d-flex align-items-center gap-2">
                                                    <a href="{{route('KategoriIssueEdit',$item->id)}}" class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1 px-3 rounded-3">
                                                    <i data-feather="edit-2" class="me-1" style="width:16px;height:16px;"></i> Edit
                                                    </a>

                                                    <button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 px-3 rounded-3" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#DeleteKategoriIssue{{$item->id}}">
                                                    <i data-feather="trash-2" class="me-1" style="width:16px;height:16px;"></i> Hapus
                                            </button>
                                            @include('superadmin/setting/KategoriIssue/modaldelete')
                                        </div>
                                        
                                            </li>
                                            @endforeach

                                            

                                            </ul>
                                            <div class="mt-3 d-flex justify-content-end">
                                            {{ $KategoriIssue->onEachSide(1)->links('pagination::bootstrap-5') }}
                                            </div>
                                            
                                    
                                    </div> <!--end col-lg-6-->                                                                       
                                </div>
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div> <!-- end col -->        
                </div> <!-- end row -->



                    @endsection