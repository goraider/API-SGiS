<?php

namespace App\Http\Controllers\V1\Transacciones;

use App\Models\Transacciones\Acompaniantes;
use App\Models\Transacciones\MovimientosIncidencias;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use \Validator,\Hash, \Response, \DB;
use Illuminate\Support\Facades\Input;

use App\Models\Transacciones\Incidencias;
use App\Models\Transacciones\Pacientes;
use App\Models\Transacciones\Responsables;
use App\Models\Transacciones\Personas;
use App\Models\Transacciones\Referencias;


/**
 * Controlador Incidencia
 *
 * @package    UGUS API
 * @subpackage Controlador
 * @author     Luis Alberto Valdez Lescieur <luisvl13@gmail.com>
 * @created    2017-05-25
 *
 * Controlador `Incidencia`: Controlador  para el manejo de incidencias
 *
 */
class IncidenciaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $parametros = Input::only('q','page','per_page');
        if ($parametros['q']) {
            $data =  Incidencias::where(function($query) use ($parametros) {
                $query->where('id','LIKE',"%".$parametros['q']."%")
                    ->orWhere('nombre','LIKE',"%".$parametros['q']."%");
            });
        } else {
            $data =  Incidencias::getModel();
        }

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $data = $data->paginate($resultadosPorPagina);
        } else {
            $data = $data->get();
        }

        return Response::json([ 'data' => $data],200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $datos = Input::json()->all();

        $success = false;
        $errors_main = array();
        DB::beginTransaction();

        try {

            $validacion = $this->ValidarParametros("", NULL, $datos);
            if($validacion != ""){
                return Response::json(['error' => $validacion], HttpResponse::HTTP_CONFLICT);
            }

            $data = new Incidencias;

            $this->AgregarDatos($datos, $data);
            $success = true;

        } catch (\Exception $e){
            return Response::json($e->getMessage(), 500);
        }

        if ($success){
            DB::commit();
            return Response::json(array("status" => 201,"messages" => "Creado","data" => $data), 201);
        } else{
            DB::rollback();
            return Response::json(array("status" => 409,"messages" => "Conflicto"), 409);
        }
    }

    /**
     * Devuelve la información del registro especificado.
     *
     * @param  int  $id que corresponde al identificador del recurso a mostrar
     *
     * @return Response
     * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
     * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
     */
    public function show($id){
        $data = Incidencias::find($id);

        $triageSintomas = TriageSintomas::where("triage_id", $id)
            ->with('triageColorTriageSintoma')
            ->get();

        $data->triage_sintomas = $triageSintomas;

        if(!$data){
            return Response::json(array("status" => 204,"messages" => "No hay resultados"), 204);
        }
        else{
            return Response::json(array("status" => 200,"messages" => "Operación realizada con exito","data" => $data), 200);
        }
    }

    /**
     * Actualizar el  registro especificado en el la base de datos
     *
     * <h4>Request</h4>
     * Recibe un Input Request con el json de los datos
     *
     * @param  int  $id que corresponde al identificador del dato a actualizar
     * @return Response
     * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
     * <code> Respuesta Error json(array("status": 304, "messages": "No modificado"),status) </code>
     */
    public function update($id){
        $validacion = $this->ValidarParametros("", $id, Input::json()->all());
        if($validacion != ""){
            return Response::json(['error' => $validacion], HttpResponse::HTTP_CONFLICT);
        }
        $datos = Request::json();
        $success = false;
        DB::beginTransaction();
        try{
            $data = Incidencias::find($id);

            $data->nombre = $datos['nombre'];
            $data->descripcion = $datos['descripcion'];

            if ($data->save())
                $success = true;
        }
        catch (\Exception $e){
            return Response::json($e->getMessage(), 500);
        }
        if ($success){
            DB::commit();
            return Response::json(array("status" => 200, "messages" => "Operación realizada con exito", "data" => $data), 200);
        }
        else {
            DB::rollback();
            return Response::json(array("status" => 304, "messages" => "No modificado"),304);
        }
    }

    /**
     * Elimine el registro especificado del la base de datos (softdelete).
     *
     * @param  int  $id que corresponde al identificador del dato a eliminar
     *
     * @return Response
     * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
     * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
     */
    public function destroy($id)
    {
        $success = false;
        DB::beginTransaction();
        try {
            $data = Incidencias::find($id);
            if($data)
                $data->delete();
            $success = true;
        }
        catch (\Exception $e){
            return Response::json($e->getMessage(), 500);
        }
        if ($success){
            DB::commit();
            return Response::json(array("status" => 200, "messages" => "Operación realizada con exito","data" => $data), 200);
        }
        else {
            DB::rollback();
            return Response::json(array("status" => 404, "messages" => "No se encontro el registro"), 404);
        }
    }

    /**
     * Validad los parametros recibidos, Esto no tiene ruta de acceso es un metodo privado del controlador.
     *
     * @param  Request  $request que corresponde a los parametros enviados por el cliente
     *
     * @return Response
     * <code> Respuesta Error json con los errores encontrados </code>
     */
    private function ValidarParametros($key, $id, $request){

        $messages = [
            'required' => 'required',
            'unique' => 'unique'
        ];

        $rules = [
            'motivo_ingreso' => 'required',
            'impresion_diagnostica' => 'required',
        ];

        $v = Validator::make($request, $rules, $messages);

        if ($v->fails()){
            $mensages_validacion = array();
            foreach ($v->errors()->messages() as $indice => $item) { // todos los mensajes de todos los campos
                $msg_validacion = array();
                foreach ($item as $msg) {
                    array_push($msg_validacion, $msg);
                }
                array_push($mensages_validacion, array($indice.''.$key => $msg_validacion));
            }
            return $mensages_validacion;
        }else{
            return ;
        }
    }

    private function AgregarDatos($datos, $data){

        //Informacion de incidencia
        $data->id = $datos['id'];
        $data->servidor_id = env("SERVIDOR_ID");
        $data->motivo_ingreso = $datos['motivo_ingreso'];
        $data->impresion_diagnostica = $datos['impresion_diagnostica'];

        if ($data->save()){
            $datos = (object) $datos;

            //verificar si existe referencias, en caso de que exista proceder a guardarlo
            if(property_exists($datos, "referencia")){
                //limpiar el arreglo de posibles nullos
                $detalleReferencia = array_filter($datos->referencia, function($v){return $v !== null;});
                //borrar los datos previos de articulo para no duplicar información
                //Referencias::where("triage_id", $data->id)->delete();
                //recorrer cada elemento del arreglo
                foreach ($detalleReferencia as $key => $value) {
                    //validar que el valor no sea null
                    if($value != null){
                        //comprobar si el value es un array, si es convertirlo a object mas facil para manejar.
                        if(is_array($value))
                            $value = (object) $value;

                        $referencia = new Referencias;

                        $referencia->servidor_id 	    = env("SERVIDOR_ID");
                        $referencia->medico_refiere_id 	= $value->medico_refiere_id;
                        $referencia->diagnostico        = $value->diagnostico;
                        $referencia->clues_origen       = $value->clues_origen;
                        $referencia->clues_destino      = $value->clues_destino;
                        $referencia->incidencias_id     = $data->id;

                        $referencia->save();

                        DB::select("insert into incidencia_clue (incidencias_id, clues) VALUE ('$data->id', '$value->clues_origen')");
                    }
                }
            }

            //verificar si existe paciente, en caso de que exista proceder a guardarlo
            if(property_exists($datos, "responsable")){
                //limpiar el arreglo de posibles nullos
                $detalleResponsable = array_filter($datos->responsable, function($v){return $v !== null;});
                //borrar los datos previos de articulo para no duplicar información
                //Referencias::where("personas_id", $data->id)->delete();
                //recorrer cada elemento del arreglo
                foreach ($detalleResponsable as $key => $value) {
                    //validar que el valor no sea null
                    if($value != null){
                        //comprobar si el value es un array, si es convertirlo a object mas facil para manejar.
                        if(is_array($value))
                            $value = (object) $value;

                        //comprobar que el dato que se envio no exista o este borrado, si existe y esta borrado poner en activo nuevamente
//                        DB::select("update triage_sintomas set deleted_at = null where triage_id = '$data->id' and nombre = '$value->nombre' ");
                        //si existe el elemento actualizar
//                        $sintoma = TriageSintomas::where("triage_id", $data->id)->where("nombre", $value->nombre)->first();
                        //si no existe crear
//                        if(!$sintoma)
                        $persona = new Personas;

                        $persona->servidor_id 	     = env("SERVIDOR_ID");
                        $persona->id                 = $value->id;
                        $persona->nombre             = $value->nombre;
                        $persona->paterno            = $value->paterno;
                        $persona->materno            = $value->materno;
                        $persona->telefono           = $value->telefono;

                        if ($persona->save()){

                            $responsable = new Responsables;

                            $responsable->servidor_id 	    = env("SERVIDOR_ID");
                            $responsable->personas_id       = $persona->id;
                            $responsable->parentescos_id    = $value->parentescos_id;

                            $responsable->save();
                        }

                    }
                }
            }

            //verificar si existe paciente, en caso de que exista proceder a guardarlo
            if(property_exists($datos, "paciente")){
                //limpiar el arreglo de posibles nullos
                $detallePaciente = array_filter($datos->paciente, function($v){return $v !== null;});
                //borrar los datos previos de articulo para no duplicar información
                //Referencias::where("triage_id", $data->id)->delete();
                //recorrer cada elemento del arreglo
                foreach ($detallePaciente as $key => $value) {
                    //validar que el valor no sea null
                    if($value != null){
                        //comprobar si el value es un array, si es convertirlo a object mas facil para manejar.
                        if(is_array($value))
                            $value = (object) $value;

                        //comprobar que el dato que se envio no exista o este borrado, si existe y esta borrado poner en activo nuevamente
//                        DB::select("update triage_sintomas set deleted_at = null where triage_id = '$data->id' and nombre = '$value->nombre' ");
                        //si existe el elemento actualizar
//                        $sintoma = TriageSintomas::where("triage_id", $data->id)->where("nombre", $value->nombre)->first();
                        //si no existe crear
//                        if(!$sintoma)
                        $persona = new Personas;

                        $persona->servidor_id 	     = env("SERVIDOR_ID");
                        $persona->id                 = $value->id;
                        $persona->nombre             = $value->nombre;
                        $persona->paterno            = $value->paterno;
                        $persona->materno            = $value->materno;
                        $persona->fecha_nacimiento   = $value->fecha_nacimiento;
                        $persona->telefono           = $value->telefono;

                        if ($persona->save()){

                            $paciente = new Pacientes;

                            $paciente->servidor_id 	     = env("SERVIDOR_ID");
                            $paciente->domicilio         = $value->domicilio;
                            $paciente->personas_id       = $persona->id;
                            $paciente->responsables_id   = $responsable->id;

                            $paciente->save();
                        }

                    }
                }
            }

            //verificar si existe paciente, en caso de que exista proceder a guardarlo
            if(property_exists($datos, "acompaniante")){
                //limpiar el arreglo de posibles nullos
                $detalleResponsable = array_filter($datos->acompaniante, function($v){return $v !== null;});
                //borrar los datos previos de articulo para no duplicar información
                //Referencias::where("triage_id", $data->id)->delete();
                //recorrer cada elemento del arreglo
                foreach ($detalleResponsable as $key => $value) {
                    //validar que el valor no sea null
                    if($value != null){
                        //comprobar si el value es un array, si es convertirlo a object mas facil para manejar.
                        if(is_array($value))
                            $value = (object) $value;

                        //comprobar que el dato que se envio no exista o este borrado, si existe y esta borrado poner en activo nuevamente
//                        DB::select("update triage_sintomas set deleted_at = null where triage_id = '$data->id' and nombre = '$value->nombre' ");
                        //si existe el elemento actualizar
//                        $sintoma = TriageSintomas::where("triage_id", $data->id)->where("nombre", $value->nombre)->first();
                        //si no existe crear
//                        if(!$sintoma)
                        $persona = new Personas;

                        $persona->servidor_id 	     = env("SERVIDOR_ID");
                        $persona->id                 = $value->id;
                        $persona->nombre             = $value->nombre;
                        $persona->paterno            = $value->paterno;
                        $persona->materno            = $value->materno;
                        $persona->telefono           = $value->telefono;

                        if ($persona->save()){

                            $acompaniante = new Acompaniantes;

                            $acompaniante->servidor_id 	    = env("SERVIDOR_ID");
                            $acompaniante->personas_id      = $persona->id;
                            $acompaniante->parentescos_id   = $value->parentescos_id;

                            if($acompaniante->save()){
                                DB::select("insert into paciente_ticket (incidencias_id, pacientes_id, acompaniantes_id) VALUE ('$data->id', '$paciente->id', '$acompaniante->id')");
                            }
                        }

                    }
                }
            }

            //verificar si existe paciente, en caso de que exista proceder a guardarlo
            if(property_exists($datos, "movimientos_incidencias")){
                //limpiar el arreglo de posibles nullos
                $detalleResponsable = array_filter($datos->movimientos_incidencias, function($v){return $v !== null;});
                //borrar los datos previos de articulo para no duplicar información
                //Referencias::where("triage_id", $data->id)->delete();
                //recorrer cada elemento del arreglo
                foreach ($detalleResponsable as $key => $value) {
                    //validar que el valor no sea null
                    if($value != null){
                        //comprobar si el value es un array, si es convertirlo a object mas facil para manejar.
                        if(is_array($value))
                            $value = (object) $value;

                        //comprobar que el dato que se envio no exista o este borrado, si existe y esta borrado poner en activo nuevamente
//                        DB::select("update triage_sintomas set deleted_at = null where triage_id = '$data->id' and nombre = '$value->nombre' ");
                        //si existe el elemento actualizar
//                        $sintoma = TriageSintomas::where("triage_id", $data->id)->where("nombre", $value->nombre)->first();
                        //si no existe crear
//                        if(!$sintoma)
                        $movientos_incidencias = new MovimientosIncidencias;

                        $movientos_incidencias->servidor_id 	                = env("SERVIDOR_ID");
                        $movientos_incidencias->incidencias_id                  = $data->id;
                        $movientos_incidencias->medico_reporta_id               = 'MED1';
                        $movientos_incidencias->indicaciones                    = $value->indicaciones;
                        $movientos_incidencias->reporte_medico                  = $value->reporte_medico;
                        $movientos_incidencias->estados_incidencias_id          = $value->estados_incidencias_id;
                        $movientos_incidencias->valoraciones_pacientes_id       = $value->valoraciones_pacientes_id;
                        $movientos_incidencias->estados_pacientes_id            = $value->estados_pacientes_id;

                        $movientos_incidencias->save();

                    }
                }
            }


        }
    }
}
