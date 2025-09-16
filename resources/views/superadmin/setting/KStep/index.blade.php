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
                                    <a href="{{route('KStepCreate')}}" class="btn btn-de-primary btn-sm">
                                    <i data-feather="plus" class="align-self-center icon-xs me-2"></i>Tambah Step Layanan</a>
                                    
                                    <a href="{{route('KIssueCreate')}}" class="btn btn-de-info btn-sm">
                                    <i data-feather="plus" class="align-self-center icon-xs me-2"></i>Tambah Issue Step Layanan</a>
                                </div><!--end /div-->

                                
                                
                            </div><!--end /div-->
                        </div><!--end col-->
                    </div><!--end row-->

                  <div class="row">
                    @foreach ($services as $svc)
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-color-success">
                                <h4 class="card-title">{{ $svc->service_name }}</h4>
                                <p class="text-muted mb-0">{{$svc->keterangan}} 
                                </p>
                                
                            </div><!--end card-header-->
                            <div class="card-body">
                                 @php $accId = 'acc-'.$svc->id; @endphp
                                <div class="accordion" id="{{$accId}}">
                                    @foreach ($svc->steps as $step)
                                    @php
                                    $i = $loop->iteration;
                                    $heading = "h-{$svc->id}-{$i}";
                                    $collapse = "c-{$svc->id}-{$i}";
                                    @endphp
                                    <div class="accordion-item">
                                        <h5 class="accordion-header m-0" id="{{$heading}}">
                                            <div class="d-flex align-items-center w-100 gap-2">
                                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#{{ $collapse }}" 
                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}" 
                                                aria-controls="{{ $collapse }}">
                                                <div class="w-100 d-flex">
                                                <div class="flex-grow">
                                                    {{ $step->step_order }}. {{ $step->service_step_name }}
                                                    <div class="small text-muted mt-1">
                                                    Durasi waktu: {{ $step->std_step_time ?? 0 }} menit
                                                    </div>
                                                </div>
                                                
                                                </div>
                                            </button>
                                            <div class="ms-2 pe-2 d-flex gap-0">  <!-- jarak dari toggle & tepi kanan -->
                                                <a href="{{route('KStepEdit',$step->id)}}" class="btn btn-sm btn-outline-warning float-end br">Edit</a>
                                                <button  class="btn btn-sm btn-outline-danger float-end ms-2" data-bs-toggle="modal" data-bs-target="#exampleModalDanger{{$step->id}}">Hapus</button>
                                            @include('superadmin/setting/KStep/modaldelete')  
                                            </div>    
                                        </div>
                                        </h5>
                                        <div id="{{ $collapse }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" 
                                            aria-labelledby="{{ $heading }}" 
                                            data-bs-parent="#{{ $accId }}">
                                            
                                            <div class="accordion-body">
                                            @php
  $totalIssue = ($step->issues ?? collect())->count();
@endphp

<div class="alert alert-info border-0" role="alert">
  @if ($totalIssue > 0)
    <div class="d-flex justify-content-between align-items-center">
      <div>Ada issue di tahapan ini</div>
      <div><strong>{{ $totalIssue }}</strong> Issue</div>
    </div>
  @else
    Tidak ada issue di tahapan ini
  @endif
</div>
                                            @foreach ($step->issues as $issue)
                                                
                                            
                                            <div class="border border-success rounded-3 p-3 bg-success bg-opacity-10 mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                <div class="fw-semibold">
                                                    {{ $issue->issue_name }} <span class="text-muted badge bg-soft-success ms-2"> {{ $issue->kategori?->category_name ?? '-' }}</span>
                                                     @if (!empty($issue->solusion))
                                                    <span class="badge bg-soft-success ms-2">Ada Solusi</span>
                                                    @else
                                                    <span class="badge bg-soft-secondary ms-2">Belum ada solusi</span>
                                                    @endif
                                                </div>
                                                <small class="text-muted"> {{ $svc->service_name }} &mdash; {{ $step->service_step_name }}</small>
                                                </div>

                                                <div class="d-flex align-items-center gap-3">
                                                <a href="{{route('KIssueEdit',$issue->id)}}" class="text-warning text-decoration-none">
                                                    <i data-feather="edit-2" class="me-1" style="width:16px;height:16px;"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger float-end ms-2" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#ModalDeleteIssue{{$issue->id}}" 
                                                >
                                                    <i data-feather="trash-2" class="me-1" style="width:16px;height:16px;"></i> Hapus
                                            </button>
                                            @include('superadmin/setting/KIssue/modaldelete')    
                                            </div>
                                                
                                            </div>

                                             @if(!empty($issue->solusion))
                                            <div class="bg-white border rounded-2 px-3 py-2 mt-2">
                                            <span class="fw-semibold">Solusi:</span>
                                            <span class="ms-1">{!! nl2br(e($issue->solusion)) !!}</span>
                                            </div>
                                            @endif

                                             @if(!is_null($issue->std_solution_time))
                                            <div class="mt-2 small text-muted">
                                            Estimasi solusi: {{ $issue->std_solution_time }} menit
                                            </div>
                                            @endif
                                            </div>
                                            @endforeach
                                            </div>
                                            </div>
                                            </div>
                                            @endforeach
                                    </div>
                                    
                                
                                
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div><!--end col-->
                    @endforeach


                </div><!--end row-->
                    @endsection