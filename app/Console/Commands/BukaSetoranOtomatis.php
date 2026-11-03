<?php

namespace App\Console\Commands;

use App\Models\Satpam;
use Illuminate\Console\Command;

class BukaSetoranOtomatis extends Command
{
    protected $signature = 'satpam:buka-setoran {--pabrik= : Batasi ke satu pabrik (wijaya|wahana)}';

    protected $description = 'Buka kunci tombol setoran di app satpam untuk tiap pabrik (dijadwalkan tiap pagi).';

    public function handle(): int
    {
        $pabriks = $this->option('pabrik')
            ? [(string) $this->option('pabrik')]
            : Satpam::query()->where('aktif', true)->distinct()->pluck('pabrik')->all();

        foreach ($pabriks as $pabrik) {
            $satpam = Satpam::where('pabrik', $pabrik)->where('aktif', true)->first();

            if (! $satpam) {
                $this->warn("{$pabrik}: tidak ada satpam aktif, dilewati.");
                continue;
            }

            if ($satpam->setoranDibuka()) {
                $this->line("{$pabrik}: masih terbuka dari sebelumnya, terus menghitung.");
                continue;
            }

            $setoran = $satpam->bukaTutupBuku(null, true);
            $this->info("{$pabrik}: tombol setoran dibuka (setoran #{$setoran->id}).");
        }

        return self::SUCCESS;
    }
}