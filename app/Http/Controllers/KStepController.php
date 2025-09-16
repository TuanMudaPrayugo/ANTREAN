<?php

namespace App\Http\Controllers;

use App\Models\KStep;
use App\Models\KLayanan;
use Database\Seeders\KStepSeeder;
use Illuminate\Http\Request;

class KStepController extends Controller
{

    public function byLayanan(Request $request)
    {
        // opsional: validasi param
        $request->validate([
            'layanan_id' => ['required','integer'],
        ]);

        $steps = KStep::where('layanan_id', $request->query('layanan_id'))
            ->orderBy('step_order')              // kalau tak ada kolom ini, ganti 'id'
            ->select('id','service_step_name')   // lebih jelas daripada get([...])
            ->get();

        return response()->json($steps);
    } // fungsi ini untuk mengambail id step layanan sesuai dengan layanan_id yang dipilih

    public function index(){

        $services = KLayanan::with(['steps' => function ($q) {
                        $q->orderBy('step_order');
                    }])
                    ->where('status_layanan', true) // opsional
                    ->orderBy('service_name')
                    ->get();


        $data=array(
                "title"=>"Data Step Layanan",
                "menuKStep"=>"active",
                'services'  => $services,   // cukup ini,

                // 'KLayanan' => KLayanan::where('status_layanan', 1)->get(),
           );


        return view('superadmin/setting/KStep/index',$data);
    }

    public function create(){
        $data=array(
           'title'=>'Tambah Data Step',
           'menuTambahsteplayanan'=>'active',
          'layanan'=>KLayanan::where('status_layanan','1')->get(),  //untuk mengambil data nama layanan yang aktif saja
        );
        
        return view('superadmin/setting/KStep/create',$data);
    }

    public function store(Request $request){
        $request->validate(
            ['layanan_id' => 'required',
            'service_step_name' => 'required',
            'step_order' => 'required',
            'std_step_time' => 'required'
            ],
            [
                'layanan_id.required' => 'Nama Layanan tidak boleh kosong',
                'service_step_name.required' => 'Nama Tahapan Layanan Tidak Boleh Kosong',
                'step_order.required' => 'Nomor Urutan Layanan tidak boleh kosong',
                'std_step_time.required' => 'Durasi Layanan tidak boleh kosong'
            ]   
        );
        $KStep= new KStep;
        $KStep->layanan_id = $request->layanan_id;
        $KStep->service_step_name = $request->service_step_name;
        $KStep->step_order = $request->step_order;
        $KStep->std_step_time = $request->std_step_time;
        $KStep->save();
        return redirect()->route('KStep')->with('success','Data Berhasil Di tambahkan');
    }

    public function edit($id){
        $data=array(
           'title'=>'Edit Data Step Layanan',
           'menuEditStepLayanan'=>'active',
           'KStep'=>KStep::findOrFail($id),
           'layanan' => KLayanan::where('status_layanan', true)
                        ->orderBy('service_name')
                        ->get(),
        );
        
        return view('superadmin/setting/KStep/edit',$data);
    }

    public function update(Request $request,$id){
        $request->validate(
            ['layanan_id' => 'required',
            'service_step_name' => 'required',
            'step_order' => 'required',
            'std_step_time' => 'required'
            ],
            [
                'layanan_id.required' => 'Nama Layanan tidak boleh kosong',
                'service_step_name.required' => 'Nama Tahapan Layanan Tidak Boleh Kosong',
                'step_order.required' => 'Nomor Urutan Layanan tidak boleh kosong',
                'std_step_time.required' => 'Durasi Layanan tidak boleh kosong'
            ]   
        );
        $KStep = KStep::findOrFail($id);
        $KStep->layanan_id = $request->layanan_id;
        $KStep->service_step_name = $request->service_step_name;
        $KStep->step_order = $request->step_order;
        $KStep->std_step_time = $request->std_step_time;
        $KStep->save();
        return redirect()->route('KStep')->with('success','Data Berhasil Di Ubah');
    }

    public function destroy($id){
        $KStep=KStep::findOrFail($id);
        $KStep->delete();

        return redirect()->route('KStep')->with('success','Data Berhasil Di Hapus');
    }



}
