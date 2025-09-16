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
                                <form action="{{route('KIssueStore')}}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                    
                                    <div class="mb-3">
                                    <label for="layanan_id">Nama Layanan</label>
                                    <select id="layanan_id" name="layanan_id" class="form-select select-layanan">
                                    <option disabled {{ old('layanan_id', request('layanan_id')) ? '' : 'selected' }}>
                                        -- Pilih Nama Layanan --
                                    </option>
                                    @foreach ($layanan as $item)
                                        <option value="{{ $item->id }}"
                                        {{ (string) old('layanan_id', request('layanan_id')) === (string) $item->id ? 'selected' : '' }}>
                                        {{ $item->service_name }}
                                        </option>
                                    @endforeach
                                            </select>

                                            <button type="submit"
                                                class="btn btn-sm btn-outline-primary mt-2"
                                                formmethod="GET"
                                                formaction="{{ route('KIssueCreate') }}">
                                        Muat Step
                                        </button>
                                            @error('layanan_id')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror                                    
                                        </div>

                                    <div class="mb-3">
                                    <label for="steplayanan_id">Nama Step Layanan</label>
                                <select id="steplayanan_id" name="steplayanan_id" class="form-select"
                                        {{ request('layanan_id') ? '' : 'disabled' }}>
                                <option disabled {{ old('steplayanan_id') ? '' : 'selected' }}>-- Pilih Nama Step Layanan --</option>
                                @foreach ($steplayanan as $s)
                                    <option value="{{ $s->id }}"
                                    {{ (string) old('steplayanan_id') === (string) $s->id ? 'selected' : '' }}>
                                    {{ $s->service_step_name }}
                                    </option>
                                    @endforeach
                                            </select>
                                            @error('steplayanan_id')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror                                    
                                        </div>

                                        <div class="mb-3">
                                    <label for="categoryissue_id">Kategori Issue</label>
                                    <select id="categoryissue_id" name="categoryissue_id" class="form-select select-layanan">
                                    <option selected disabled>
                                        -- Pilih Nama Layanan --
                                    </option>
                                    @foreach ($KategoriIssue as $kat)
                                        <option value="{{ $kat->id }}"
                                        @selected(old('categoryissue_id', $issue->categoryissue_id ?? null) == $kat->id)>
                                        {{ $kat->category_name }}
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
                                        name="issue_name" id="issue_name">
                                        @error('issue_name')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror
                                    </div>


                                    <div class="mb-3">
                                        <label for="solusion">Solusi Masalah</label>
                                        
                                        <textarea type="text" class="form-control" rows="5" id="solusion" placeholder="Masukkan Solusi Masalah" 
                                        name="solusion" id="solusion"></textarea>
                                        @error('solusion')
                                        <small class="text-danger">
                                        {{$message}}
                                        </small>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="std_solution_time">Durasi Penyelesaian Solusi</label>
                                        <input type="number" class="form-control" placeholder="Masukkan Durasi Waktu Layanan" 
                                        name="std_solution_time" id="std_solution_time">
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