<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Reporte;
use Storage;

class DeleteReporte extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:reporte';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina los reportes antiguos';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        // Se recupera los reportes de la DB
        $reportes = Reporte::all();

        // Se establece la fecha limite
        $dateLimit = strtotime("-1 month");

        foreach ($reportes as $reporte) {
            # code...
            $dateReporte = strtotime($reporte->createdDate);

            if($dateReporte < $dateLimit){

                $filename = $reporte->nameReporte;

                if(file_exists(storage_path('app/public/') . $filename)){
                    // Se elimina el archivo
                    Storage::delete('public/'.$filename);
                }

                // Se elimina el registro de la DB
                Reporte::where('id', $reporte->id)->delete();
            }

        }

        return 0;
    }
}
