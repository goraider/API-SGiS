<?php

namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;

class SisUsuariosNotificaciones extends Model {

	public function SisUsuario(){
		return $this->belongsTo('App\Models\Sistema\SisUsuario','sis_usuarios_id','id');
    }
}
