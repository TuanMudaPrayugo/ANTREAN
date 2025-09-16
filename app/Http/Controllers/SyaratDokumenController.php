<?php

namespace App\Http\Controllers;

use App\Models\KLayanan;
use Illuminate\Http\Request;
use App\Models\SyaratDokumen;
use Illuminate\Support\Facades\DB;

class SyaratDokumenController extends Controller
{
    public function index(){

        $docs = SyaratDokumen::with([
                'layanan' => fn($q) => $q->select('id', 'service_name', 'status_layanan')
            ])
            ->whereHas('layanan', fn($q) => $q->where('status_layanan', 1))   // <- filter aktif
            ->get(); 
        $data=array(
                "title"=>"Data Syarat Dokumen",
                "menuSyaratDokumen"=>"active",
                 'grouped' => $docs->groupBy(fn($d) => $d->layanan->service_name),
                 
           );


        return view('superadmin/setting/SyaratDokumen/index',$data);
    }

    public function create(){
        $data=array(
           'title'=>'Tambah Data Syarat Dokumen Layanan',
           'menuTambahsteplayanan'=>'active',
          'layanan'=>KLayanan::where('status_layanan','1')->get(),  //untuk mengambil data nama layanan yang aktif saja
        );
        
        return view('superadmin/setting/SyaratDokumen/create',$data);
    }

    public function store(Request $request){
        $request->validate(
            ['layanan_id' => 'required',
            'require_document' => 'required'
           
            ],
            [
                'layanan_id.required' => 'Nama Layanan tidak boleh kosong',
                'require_document.required' => 'Nama syarat dokumen tidak boleh kosong'
            ]   
        );
        $SyaratDokumen= new SyaratDokumen;
        $SyaratDokumen->layanan_id = $request->layanan_id;
        $SyaratDokumen->require_document = $request->require_document;
        
        $SyaratDokumen->save();
        return redirect()->route('SyaratDokumen')->with('success','Data Berhasil Di tambahkan');
    }

    public function edit($id){
        $data=array(
           'title'=>'Edit Data Step Layanan',
           'menuEditStepLayanan'=>'active',
           'SyaratDokumen'=>SyaratDokumen::findOrFail($id),
           'layanan' => KLayanan::where('status_layanan', true)
                        ->orderBy('service_name')
                        ->get(),
        );
        
        return view('superadmin/setting/SyaratDokumen/edit',$data);
    }

    public function update(Request $request,$id){
        $request->validate(
            ['layanan_id' => 'required',
            'require_document' => 'required'
            ],
            [
                'layanan_id.required' => 'Nama Layanan tidak boleh kosong',
                'require_document.required' => 'Nama Tahapan Layanan Tidak Boleh Kosong'
            ]   
        );
        $SyaratDokumen = SyaratDokumen::findOrFail($id);
        $SyaratDokumen->layanan_id = $request->layanan_id;
        $SyaratDokumen->require_document = $request->require_document;
        
        $SyaratDokumen->save();
        return redirect()->route('SyaratDokumen')->with('success','Data Berhasil Di Ubah');
    }

    public function destroy($id){
        $SyaratDokumen=SyaratDokumen::findOrFail($id);
        $SyaratDokumen->delete();

        return redirect()->route('KStep')->with('success','Data Berhasil Di Hapus');
    }

}
