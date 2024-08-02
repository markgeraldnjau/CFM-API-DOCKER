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
use DB;
use Illuminate\Http\Request;
use Log;

class GeneralController extends Controller
{
  //
  public function cfmRegions(Request $request)
  {
    try {
      $cfm_regions = CfmRegion::select('id', 'region_code', 'region_name')->get();

      Log::info("cfm data are ");
      Log::info($cfm_regions);
      return response()->json($cfm_regions);
    } catch (\Throwable $th) {
      //throw $th;
      Log::error($th->getMessage());
      return response()->json($th->getMessage());

    }
  }
  public function farePriceCategories(Request $request)
  {
    try {
      $fare_price_categories = FarePriceCategory::select('id', 'price_formula', 'more')->get();

      Log::info("fare_price_categories data are ");
      Log::info($fare_price_categories);
      return response()->json($fare_price_categories);
    } catch (\Throwable $th) {
      //throw $th;
      Log::error($th->getMessage());
      return response()->json($th->getMessage());

    }
  }
  public function trainDirections(Request $request)
  {
    try {
      $train_directions = TrainDirection::select('id', 'name')->get();

      Log::info("train_directions data are ");
      Log::info($train_directions);
      return response()->json($train_directions);
    } catch (\Throwable $th) {
      //throw $th;
      Log::error($th->getMessage());
      return response()->json($th->getMessage());

    }
  }

  public function genders(Request $request)
  {
    try {
      $genders = Gender::select('id', 'gender')->get();

      Log::info("genders data are ");
      Log::info($genders);
      return response()->json($genders);
    } catch (\Throwable $th) {
      //throw $th;
      Log::error($th->getMessage());
      return response()->json($th->getMessage());

    }
  }

  public function deviceTypes(Request $request)
  {
    try {
      $deviceTypes = DeviceType::select('id', 'type_id', 'description')->get();
      Log::info("deviceTypes data are ");
      Log::info($deviceTypes);
      return response()->json($deviceTypes);
    } catch (\Throwable $th) {
      //throw $th;
      Log::error($th->getMessage());
      return response()->json($th->getMessage());

    }
  }

  public function operatorTypes(Request $request)
  {
    try {
      $operatorTypes = OperatorType::select('id', 'name', 'code')->latest('id')->get();
      return response()->json($operatorTypes);
    } catch (\Throwable $th) {
      //throw $th;
      \Illuminate\Support\Facades\Log::error($th->getMessage());
      return response()->json($th->getMessage());

    }
  }

  public function onOffTickets(Request $request)
  {
    try {
        $onOffTickets = DB::table('train_sales_types')->get();

      Log::info("onOffTickets data are ");
      Log::info($onOffTickets);
      return response()->json($onOffTickets);
    } catch (\Throwable $th) {
      //throw $th;
      Log::error($th->getMessage());
      return response()->json($th->getMessage());

    }
  }

  public function operatorCategory(Request $request){
    try {
        $operatorCategories = \Illuminate\Support\Facades\DB::table('operator_categories')->get();

      \Illuminate\Support\Facades\Log::info("operatorCategories data are ");
      \Illuminate\Support\Facades\Log::info($operatorCategories);
      return response()->json($operatorCategories);
    } catch (\Throwable $th) {
      \Illuminate\Support\Facades\Log::error($th->getMessage());
      return response()->json($th->getMessage());

    }
  }

  public function provinces(Request $request)
  {
    try {
      $provinces = Province::select('id', 'name', )->latest('id')->get();

      Log::info("provinces data are ");
      Log::info($provinces);
      return response()->json($provinces);
    } catch (\Throwable $th) {
      //throw $th;
      Log::error($th->getMessage());
      return response()->json($th->getMessage());

    }
  }
}
