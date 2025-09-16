                                    <div class="modal fade" id="exampleModalDanger{{$item->id}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalDanger1" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger">
                                                <h6 class="modal-title m-0 text-white" id="exampleModalDanger1">Hapus {{$title}}</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div><!--end modal-header-->
                                            <div class="modal-body">
                                                <div class="row">
                                                    
                                                    <div class="col-lg-9">
                                                        <h5>{{$item->service_name}}</h5>
                                                        @if ($item->status_layanan==true)
                                                        <span class="badge bg-soft-success">
                                                            Aktif
                                                        </span>    
                                                        @else
                                                        <span class="badge bg-soft-danger">
                                                        Tidak Aktif
                                                        </span>
                                                        @endif
                                                        
                                                        <ul class="mt-3 mb-0">
                                                            <li>Keterangan : {{$item->keterangan}}</li>
                                                            
                                                        </ul>
                                                    </div><!--end col-->
                                                </div><!--end row-->                                                     
                                            </div><!--end modal-body-->
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-de-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                <a href="{{route('KLayananDestroy',$item->id)}}"  type="button" class="btn btn-de-danger btn-sm">Hapus</a>
                                            </div><!--end modal-footer-->
                                        </div><!--end modal-content-->
                                    </div><!--end modal-dialog-->
                                </div><!--end modal-->