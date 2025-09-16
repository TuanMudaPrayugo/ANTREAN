                                    <div class="modal fade" id="DeleteKategoriIssue{{$item->id}}" tabindex="-1" role="dialog" aria-labelledby="DeleteKategoriIssue{{$item->id}}" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger">
                                                <h6 class="modal-title m-0 text-white" id="DeleteKategoriIssue">Hapus {{$title}}</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div><!--end modal-header-->
                                            <div class="modal-body text-center">
                                                <div class="row">
                                                    
                                                    <div class="col-lg-9">
                                                        <h5>Apakah anda yakin menghapus kategori " 
                                                            <span class="fw-bold">{{$item->category_name}}</span>" ini ?</h5>
                                                        
                                                        
                                                        
                                                    </div><!--end col-->
                                                </div><!--end row-->                                                     
                                            </div><!--end modal-body-->
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-de-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                <a href="{{route('KategoriIssueDestroy',$item->id)}}"  type="button" class="btn btn-de-danger btn-sm">Hapus</a>
                                            </div><!--end modal-footer-->
                                        </div><!--end modal-content-->
                                    </div><!--end modal-dialog-->
                                </div><!--end modal-->