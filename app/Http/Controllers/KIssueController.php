<?php

namespace App\Http\Controllers;

use App\Models\KStep;
use App\Models\KIssue;
use App\Models\KLayanan;
use Illuminate\Http\Request;
use App\Models\KategoriIssue;
use Illuminate\Validation\Rule;

class KIssueController extends Controller
{
    public function index()
{
    
    $services = KLayanan::with([
        'steps' => fn($q) => $q->orderBy('step_order'),
        'steps.issues' => fn($q) => $q->orderBy('id')
    ])
    ->where('status_layanan', true)   // opsional, sesuai punyamu
    ->orderBy('service_name')
    ->get();

    return view('superadmin/setting/KStep/index', compact('services'));
}
public function create(Request $request){
        $data=array(
           'title'=>'Tambah Data Step',
           'menuTambahIssuelayanan'=>'active',
           'layanan'=>KLayanan::where('status_layanan','1')->get(), //menampilkan nama layana yang statusnya 1 atau aktif
            'categoryissue'=>KategoriIssue::get(),
           //    'steplayanan'=>KStep::get(),

           // hanya step milik layanan yang dipilih (atau kosong jika belum pilih)
            'steplayanan'             => KStep::when($request->layanan_id, function ($q) use ($request) {
                                        $q->where('layanan_id', $request->layanan_id);
                                    })
                                    ->orderBy('step_order')   // ganti 'id' kalau tak punya kolom ini
                                    ->get(),
           
          'selected'               => $request->layanan_id,   // <-- INI WAJIB, dipakai di Blade
          'KategoriIssue'=>KategoriIssue::get(),
        );
        
        return view('superadmin/setting/KIssue/create',$data);
    }

    public function store(Request $request){
        $request->validate(
            ['layanan_id' => 'required',
            'steplayanan_id' => [
                'required',
                Rule::exists('k_steps','id')->where(fn($q) =>
                $q->where('layanan_id', $request->layanan_id))],
             'categoryissue_id'=>'required',   
            'issue_name' => 'required',
             'solusion' => 'required',
             'std_solution_time' => 'required'
            
            ],
            [
                'layanan_id.required' => 'Nama Layanan tidak boleh kosong',
                'steplayanan_id.required' => 'Step Layanan Tidak Boleh Kosong',
                'categoryissue_id.required' => 'kategori issue Tidak Boleh Kosong',
                'issue_name.required' => 'Masalah tidak boleh kosong',
                'solusion.required' => 'Solusi Masalah tidak boleh kosong',
                'std_solution_time.required' => 'Waktu Penyelesaian Masalah tidak boleh kosong'
            ]   
        );
        $KIssue= new KIssue;
        $KIssue->layanan_id = $request->layanan_id;
        $KIssue->steplayanan_id = $request->steplayanan_id;
        $KIssue->categoryissue_id = $request->categoryissue_id;
        $KIssue->issue_name = $request->issue_name;
        $KIssue->solusion = $request->solusion;
        $KIssue->std_solution_time = $request->std_solution_time;
        $KIssue->save();
        return redirect()->route('KStep')->with('success','Data Berhasil Di tambahkan');
    }

    public function edit($id){
        
        $data=array(
           'title'=>'Edit Data Step Layanan',
           'menuEditStepLayanan'=>'active',
           'KIssue'=>KIssue::findOrFail($id),
           'categoryissue' => KategoriIssue::orderBy('category_name')->get(),
           
        );
        
        return view('superadmin/setting/KIssue/edit',$data);
                
    }

    public function update(Request $request,$id){
        $request->validate(
            [   'layanan_id' => 'prohibited',
                'steplayanan_id' => 'prohibited',
                'categoryissue_id' => 'required',
                'issue_name' => 'required',
            'solusion' => 'required',
            'std_solution_time' => 'required'
            ],
            [
                'issue_name.required' => 'Nama Layanan tidak boleh kosong',
                'solusion.required' => 'Nama Tahapan Layanan Tidak Boleh Kosong',
                'std_solution_time.required' => 'Nomor Urutan Layanan tidak boleh kosong'
                
            ]   
        );
        $KIssue = KIssue::findOrFail($id);
        $KIssue->categoryissue_id = $request->categoryissue_id;
        $KIssue->issue_name = $request->issue_name;
        $KIssue->solusion = $request->solusion;
        $KIssue->std_solution_time = $request->std_solution_time;
        
        $KIssue->save();
        return redirect()->route('KStep')->with('success','Data Berhasil Di tambahkan');

}

        public function destroy($id){
        $KIssue=KIssue::findOrFail($id);
        $KIssue->delete();

        return redirect()->route('KStep')->with('success','Data Berhasil Di Hapus');
    }

}
