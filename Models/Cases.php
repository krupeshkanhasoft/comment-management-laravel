<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cases extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'case_title',
        'case_id',
        'category_id',
        'description',
        'by_whom',
        'involved',
        'no_observed',
        'find_from',
        'status',
        'user_id',
        'duration_start_date',
        'duration_end_date',
        'is_resolved',
        'is_anonymous',
        'victimisations_id',
        'victimisations_status',
        'victimisations_description',
        'is_read_request',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_by', 'updated_at',
    ];

    /**
     * @var array
     */
    protected $casts = [
        "active" => "boolean",
        "is_waiting_list" => "boolean",
    ];

    /**
     * category function
     *
     * @return category-list
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * casesattachments function
     *
     * @return cases attachment list
     */
    public function casesattachments()
    {
        return $this->hasMany(CasesAttachments::class);
    }

    /**
     * comments function
     *
     * @return comments list
     */
    public function comments()
    {
        return $this->hasMany(Comments::class)->orderBy('id', 'DESC');
    }

    /**
     * user function
     *
     * @return user list
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * victimisation function
     *
     * @return victimisation list
     */
    public function victimisation()
    {
        return $this->hasOne(Victimisation::class, 'id', 'victimisations_id');
    }
        /**
     * @param string $options
     */
    public function getAttribute($key)
    {
        switch ($key) {
            case "profilePicture":
                return $this->getProfilePicture(); // calls dependent function
            case "inviteSent":
                return UserInvite::where([["user_id", "=", $this->attributes['id']]])->get();
        }
        return parent::getAttribute($key);
    }

    /**
     * @return string
     */
    public function getProfilePicture()
    {
        try {
            $profileImageObj = $this->documents->where("type", "profile")->first();
            if (empty($profileImageObj)) {
                throw new \Exception("No profile picture");
            }

            $profileImage = Storage::disk('s3')->temporaryUrl(
                $profileImageObj->path, now()->addMinutes(60)
            );

            return $profileImage;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * @return array
     */
    public function toArray()
    {
        $response = [];
        $arr = ['user_id', 'railsbank_user_id', 'webhook_transaction_id', 'first_transaction_request', 'first_transaction_response', 'payment_status', 'outstanding_amount', 'is_join_through_promocode', 'activation_amount', 'activatation_date', 'user_created_at', 'webhook_reference_id', 'first_deposit_date'];
        foreach ($arr as $key) {
            if ($key == 'first_transaction_request' || $key == 'first_transaction_response') {
                $response[$key] = json_decode($this->{$key});
            } else {
                $response[$key] = $this->{$key};
            }
        }
        return $response;
    }
    
}
