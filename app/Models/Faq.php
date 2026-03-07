<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use App\Services\HelperService;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Faq extends Model
{
    use HasFactory, HasAppTimezone, ManageTranslations;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'question',
        'answer'
    ];

    /**
     * Translations relationship
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslatedQuestionAttribute(){
        return HelperService::getTranslatedData($this, $this->question, 'question');
    }

    public function getTranslatedAnswerAttribute(){
        return HelperService::getTranslatedData($this, $this->answer, 'answer');
    }
}
