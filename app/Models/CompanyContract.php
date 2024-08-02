<?
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyContract extends Model
{
    use SoftDeletes;


    // protected $guarded = [];

    protected $table = 'company_contracts';
}
