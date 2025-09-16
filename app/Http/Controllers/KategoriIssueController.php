<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KategoriIssue;

class KategoriIssueController extends Controller
{
    public function index(){
        
        $data=array(
                "title"=>"Data Kategori Issue",
                "menuKLayanan"=>"active",
                'KategoriIssue'=>KategoriIssue::orderBy('category_name','asc')
                        ->paginate(5)              //menampilkan 5 data perhalaman
                        ->withQueryString(),       // jaga query string (opsional)
                
           );


        return view('superadmin/setting/KategoriIssue/index',$data);
    }

    public function create(){
        $data=array(
           'title'=>'Tambah Data Kategori Layanan',
           'menuTambahlayanan'=>'active',
           
        );
        
        return view('superadmin/setting/KategoriIssue/create',$data);
    }

    public function store(Request $request){
        $request->validate(
            ['category_name' => 'required'
            
            ],
            [
                'category_name.required' => 'Nama Kategory tidak boleh kosong'
                
            ]   
        );
        $KategoriIssue= new KategoriIssue;
        $KategoriIssue->category_name = $request->category_name;
        
        $KategoriIssue->save();
        return redirect()->route('KategoriIssue')->with('success','Data Berhasil Di tambahkan');
    }

    public function edit($id){
        $data=array(
           'title'=>'Edit Data Kategori',
           'menuEditLayanan'=>'active',
           'KategoriIssue'=>KategoriIssue::findOrFail($id),
        );
        
        return view('superadmin/setting/KategoriIssue/edit',$data);
    }

    public function update(Request $request,$id){
        $request->validate(
            ['category_name' => 'required'
            ],
            [
                'category_name.required' => 'Nama Kategory Issue tidak boleh kosong'
            ]   
        );
        $KategoriIssue = KategoriIssue::findOrFail($id);
        $KategoriIssue->category_name = $request->category_name;
        
        $KategoriIssue->save();
        return redirect()->route('KategoriIssue')->with('success','Data Berhasil Di Edit');
    }

       public function destroy($id){
        $KategoriIssue=KategoriIssue::findOrFail($id);
        $KategoriIssue->delete();

        return redirect()->route('KategoriIssue')->with('success','Data Berhasil Di Hapus');
    }

}
