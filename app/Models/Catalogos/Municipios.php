<?php

namespace App;

namespace App\Models\Catalogos;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Municipios extends BaseModel
{
    use SoftDeletes;

    protected $generarID = false;
    protected $guardarIDServidor = false;
    protected $guardarIDUsuario = false;
    public $incrementing = true;

    protected $table = "municipios";
    protected $fillable = ["id", "clave", "nombre", "jurisdicciones_id"];

    public function jurisdiccion()
    {
        return $this->belongsTo(Jurisdicciones::class,'jurisdicciones_id','id');
    }
}
