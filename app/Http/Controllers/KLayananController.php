<?php

namespace App\Http\Controllers;

use App\Models\KLayanan;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;

class KLayananController extends Controller
{
    public function index(){
        $data=array(
                "title"=>"Data Layanan",
                "menuKLayanan"=>"active",
                'KLayanan'=>KLayanan::get(),
                // 'KLayanan' => KLayanan::where('status_layanan', 1)->get(),
           );


        return view('superadmin/setting/KLayanan/index',$data);
    }

    public function create(){
        $data=array(
           'title'=>'Tambah Data Layanan',
           'menuTambahlayanan'=>'active',
           
        );
        
        return view('superadmin/setting/KLayanan/create',$data);
    }

    public function store(Request $request){
        $request->validate(
            ['service_name' => 'required',
            'keterangan' => 'required'
            ],
            [
                'service_name.required' => 'Nama Layanan tidak boleh kosong',
                'keterangan.required' => 'Nama Keterangan tidak boleh kosong'
            ]   
        );
        $KLayanan= new KLayanan;
        $KLayanan->service_name = $request->service_name;
        $KLayanan->keterangan = $request->keterangan;
        $KLayanan->status_layanan = true;
        $KLayanan->save();
        return redirect()->route('KLayanan')->with('success','Data Berhasil Di tambahkan');
    }

    public function edit($id){
        $data=array(
           'title'=>'Edit Data Layanan',
           'menuEditLayanan'=>'active',
           'KLayanan'=>KLayanan::findOrFail($id),
        );
        
        return view('superadmin/setting/KLayanan/edit',$data);
    }

    public function update(Request $request,$id){
        $request->validate(
            ['service_name' => 'required',
            'keterangan' => 'required'
            ],
            [
                'service_name.required' => 'Nama Layanan tidak boleh kosong',
                'keterangan.required' => 'Nama Keterangan tidak boleh kosong'
            ]   
        );
        $KLayanan = KLayanan::findOrFail($id);
        $KLayanan->service_name = $request->service_name;
        $KLayanan->keterangan = $request->keterangan;
        $KLayanan->status_layanan = $request->status_layanan;
        $KLayanan->save();
        return redirect()->route('KLayanan')->with('success','Data Berhasil Di Edit');
    }

    public function destroy($id){
        $KLayanan=KLayanan::findOrFail($id);
        $KLayanan->delete();

        return redirect()->route('KLayanan')->with('success','Data Berhasil Di Hapus');
    }
}
