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
                                <form action="{{route('SyaratDokumenStore')}}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                    
                                    <div class="mb-3">
                                    <label for="layanan_id">Nama Layanan</label>
                                    <select id="default" name="layanan_id">
                                    <option selected disabled >-- Pilih Nama Layanan --</option>
                                    @foreach ($layanan as $item)
                                    <option value="{{$item->id}}">{{$item->service_name}}</option>
                                    @endforeach
                                            </select>
                                            @error('layanan_id')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror                                    
                                        </div>

                                    <div class="mb-3">
                                        <label for="require_document">Masukkan Nama Syarat Layanan</label>
                                        <input type="text" class="form-control" placeholder="Masukkan nama syarat layanan" 
                                        name="require_document" id="require_document">
                                        @error('service_step_name')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror
                                    </div>

                                    

                                    

                                    </div>
                                    <a href="{{route('KStep')}}" class="btn btn-sm btn-outline-danger float-end ms-2">Batal</a>
                                    {{-- <a href="" class="btn btn-sm btn-outline-info float-end">Simpan</a> --}}
                                    <button type="submit" class="btn btn-sm btn-outline-info float-end">simpan</button>
                                </form>                                           
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div><!--end col-->
                    
                </div><!--end row-->
                    
                    @endsection