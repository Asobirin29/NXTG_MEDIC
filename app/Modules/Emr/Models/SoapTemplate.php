<?php

namespace App\Modules\Emr\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Quick-text SOAP agar dokter mengetik lebih cepat (PRD §11.1). */
class SoapTemplate extends Model
{
    use BelongsToTenant;

    /** Template bawaan untuk faskes baru: [bagian, judul, isi]. */
    public const DEFAULTS = [
        ['subjective', 'Batuk pilek', "Batuk sejak ... hari, berdahak/tidak. Pilek (+), demam (-/+). Sesak (-). Riwayat kontak (-)."],
        ['subjective', 'Demam', "Demam sejak ... hari, naik turun/terus menerus. Menggigil (-/+). Mual muntah (-). Nyeri kepala (-/+). BAB/BAK normal."],
        ['subjective', 'Kontrol hipertensi', "Kontrol rutin hipertensi. Keluhan pusing (-), nyeri dada (-). Minum obat teratur (+/-). Obat terakhir: ..."],
        ['subjective', 'Nyeri ulu hati', "Nyeri ulu hati sejak ... hari, terutama setelah/sebelum makan. Mual (+/-), muntah (-). BAB hitam (-)."],
        ['objective', 'Pemeriksaan fisik normal', "KU baik, compos mentis. Kepala/leher dbn. Thorax: cor & pulmo dbn. Abdomen: supel, BU (+) normal, NT (-). Ekstremitas: akral hangat, edema (-)."],
        ['objective', 'Faring hiperemis', "Faring hiperemis (+), tonsil T1-T1 tidak hiperemis. Rhonki (-), wheezing (-)."],
        ['plan', 'Edukasi umum', "Istirahat cukup, minum air putih yang cukup. Kontrol bila keluhan tidak membaik dalam 3 hari atau memberat."],
        ['plan', 'Edukasi hipertensi', "Diet rendah garam, olahraga teratur, minum obat rutin. Kontrol 1 bulan lagi atau bila ada keluhan."],
    ];

    protected $fillable = ['section', 'title', 'body', 'created_by'];
}
