<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notifications extends Model
{
    use HasFactory, HasAppTimezone;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $table = 'notification';

    protected $fillable = [
        'title',
        'message',
        'image',
        'type',
        'send_type',
        'customers_id',
        'propertys_id',
        'created_at',
        'updated_at',
    ];


    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function property(){
        return $this->belongsTo(Property::class,'propertys_id');
    }


    public function getImageAttribute($image)
    {
        $path = $image ? config('global.NOTIFICATION_IMG_PATH') . $image : null;
        return !empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function getCustomerDataAttribute(){
        if($this->customers_id){
            $customerId = explode(',',$this->customers_id);
            return Customer::whereIn('id',$customerId)->select('id','name')->get();
        }
        return null;
    }
}
