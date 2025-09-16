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
                        <div class="card">
                            <div class="card-header bg-blue text-white">
                                <h4 class="card-title"></h4>
                                <p class="text-muted mb-0"></p> 
                            </div><!--end card-header-->
                            <div class="card-body">
                                <form action="{{route('KategoriIssueStore')}}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                    <div class="mb-3">
                                        <label for="category_name">Nama Kategori</label>
                                        <input type="text" class="form-control" placeholder="Masukkan Nama Kategori" 
                                        name="category_name" id="category_name">
                                        @error('category_name')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror
                                    </div>

                                    </div>
                                    <a href="{{route('KategoriIssue')}}" class="btn btn-sm btn-outline-danger float-end ms-2">Batal</a>
                                    {{-- <a href="" class="btn btn-sm btn-outline-info float-end">Simpan</a> --}}
                                    <button type="submit" class="btn btn-sm btn-outline-info float-end">simpan</button>
                                </form>                                           
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div><!--end col-->
                    
                </div><!--end row-->
                    
                    @endsection