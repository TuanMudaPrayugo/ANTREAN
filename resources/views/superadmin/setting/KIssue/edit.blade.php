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
                                <form action="{{route('KIssueUpdate',$KIssue->id)}}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                    
                                   <div class="mb-3">
                                    <label for="categoryissue_id">Nama Layanan</label>
                                    <select id="default" name="categoryissue_id">
                                    <option disabled {{ old('categoryissue_id', $KIssue->categoryissue_id) ? '' : 'selected' }}>-- Pilih Nama Layanan --</option>
                                    @foreach ($categoryissue as $ci)
                                    <option value="{{$ci->id}}" {{ old('categoryissue_id', $KIssue->categoryissue_id) == $ci->id ? 'selected' : '' }}>
                                    {{ $ci->category_name }}
                                    </option>
                                    @endforeach
                                            </select>
                                            @error('categoryissue_id')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror                                    
                                        </div>

                                    <div class="mb-3">
                                        <label for="issue_name">Masalah yang terjadi</label>
                                        <input type="text" class="form-control" placeholder="Masukkan Nama Step Layanan" 
                                        name="issue_name" id="issue_name" value="{{$KIssue->issue_name}}">
                                        @error('issue_name')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror
                                    </div>


                                    <div class="mb-3">
                                        <label for="solusion">Solusi Masalah</label>
                                        
                                        <textarea type="text" class="form-control" rows="5" id="solusion" placeholder="Masukkan Solusi Masalah" 
                                        name="solusion" >{{ old('solusion', $KIssue->solusion) }}</textarea>
                                        @error('solusion')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="std_solution_time">Durasi Penyelesaian Solusi</label>
                                        <input type="number" class="form-control" placeholder="Masukkan Durasi Waktu Layanan" 
                                        name="std_solution_time" id="std_solution_time" value="{{$KIssue->std_solution_time}}">
                                        @error('std_solution_time')
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
                    
                <script>
  $('.select-layanan').select2();
</script>

                    @endsection