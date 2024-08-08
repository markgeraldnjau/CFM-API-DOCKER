<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\CfmRegion;
use App\Models\DeviceType;
use App\Models\FarePriceCategory;
use App\Models\Gender;
use App\Models\Operator\OperatorType;
use App\Models\Province;
use App\Models\TrainDirection;
use App\Traits\CommonTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class GeneralController extends Controller
{
    use CommonTrait;
  //
  public function cfmRegions()
  {
    try {
      $cfm_regions = CfmRegion::select('id', 'region_code', 'region_name')->get();
      Log::info("CFM_DATA",['DATA'=>$cfm_regions]);
      return response()->json($cfm_regions);
    } catch (\Throwable $th) {
        Log::error(json_encode($this->errorPayload($th)));
        return response()->json($th->getMessage());
    }
  }
  public function farePriceCategories()
  {
    try {
      $fare_price_categories = FarePriceCategory::select('id', 'price_formula', 'more')->get();

      Log::info("fare_price_categories_data",['DATA'=>$fare_price_categories]);
      return response()->json($fare_price_categories);
    } catch (\Throwable $th) {
        Log::error(json_encode($this->errorPayload($th)));
        return response()->json($th->getMessage());
    }
  }
  public function trainDirections()
  {
    try {
      $train_directions = TrainDirection::select('id', 'name')->get();

      Log::info("train_directions_data",['DATA'=>$train_directions]);
      return response()->json($train_directions);
    } catch (\Throwable $th) {
        Log::error(json_encode($this->errorPayload($th)));
      return response()->json($th->getMessage());
    }
  }

  public function genders()
  {
    try {
      $genders = Gender::select('id', 'gender')->get();

      Log::info("genders_data",['DATA'=>$genders]);
      return response()->json($genders);
    } catch (\Throwable $th) {
        Log::error(json_encode($this->errorPayload($th)));
      return response()->json($th->getMessage());
    }
  }

  public function deviceTypes()
  {
    try {
      $deviceTypes = DeviceType::select('id', 'type_id', 'description')->get();
      Log::info("deviceTypes_data",['DATA'=>$deviceTypes]);
;
      return response()->json($deviceTypes);
    } catch (\Throwable $th) {
        Log::error(json_encode($this->errorPayload($th)));
      return response()->json($th->getMessage());
    }
  }

  public function operatorTypes()
  {
    try {
      $operatorTypes = OperatorType::select('id', 'name', 'code')->latest('id')->get();
      return response()->json($operatorTypes);
    } catch (\Throwable $th) {
        Log::error(json_encode($this->errorPayload($th)));
      return response()->json($th->getMessage());
    }
  }

  public function onOffTickets()
  {
    try {
        $onOffTickets = DB::table('train_sales_types')->get();

      Log::info("onOffTickets_data",['DATA'=>$onOffTickets]);

      return response()->json($onOffTickets);
    } catch (\Throwable $th) {
        Log::error(json_encode($this->errorPayload($th)));
      return response()->json($th->getMessage());
    }
  }

  public function operatorCategory(){
    try {
        $operatorCategories = DB::table('operator_categories')->get();

      Log::info("operatorCategories_data",['DATA'=>$operatorCategories]);

      return response()->json($operatorCategories);
    } catch (\Throwable $th) {
        Log::error(json_encode($this->errorPayload($th)));
      return response()->json($th->getMessage());
    }
  }

  public function provinces()
  {
    try {
      $provinces = Province::select('id', 'name', )->latest('id')->get();

      Log::info("provinces_data",['DATA'=>$provinces]);
      return response()->json($provinces);
    } catch (\Throwable $th) {
        Log::error(json_encode($this->errorPayload($th)));
      return response()->json($th->getMessage());
    }
  }
}
