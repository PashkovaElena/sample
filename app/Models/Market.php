<?php

namespace App\Models;

use Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Class Market
 * @package App\Models
 */
class Market extends AppModel
{
    use ValidatesRequests;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'markets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'languages_id'];

    /**
     * @var array
     */
    protected $rules = [
        'name' => 'required|unique:markets,name|min:3|max:50',
    ];

    /**
     * Relation markets -> market_user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function marketUser()
    {
        return $this->hasMany('App\Models\MarketUser');
    }
    
    /**
     * @return mixed
     */
    public static function getMarketsQuery()
    {
        return Market::with([
            'marketSettings',
            'marketsLanguages',
            'users.role',
            'users' => function ($q) {
                $q->join('roles', 'roles.id', '=', 'users.role_id')
                    ->where('roles.name', '=', Role::MEMBER_NAME);
            },
            'marketUser' => function ($query) {
                $query->join('users', 'users.id', '=', 'market_user.user_id')
                    ->join('roles', 'roles.id', '=', 'users.role_id')
                    ->where('roles.name', '=', Role::COACH_NAME);
            }])
            ->orderBy('created_at', 'DESC');
    }

    /**
     * Get list of markets with users and market settings and pagination
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getMarketsPagination()
    {
        $markets = self::getMarketsQuery()
            ->paginate(self::ITEMS_PER_PAGE);

        $markets->setPath(route('markets.index', [], false));

        return $markets;
    }

    /**
     * Get list of markets with users and market settings
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getMarkets()
    {
        return self::getMarketsQuery()
            ->get();
    }

    /**
     * Get list of all markets
     *
     * @return mixed
     */
    public static function getMarketsList()
    {
        if (Auth::user()->isAdmin()) {
            return Market::orderBy('created_at')
                ->lists('name', 'id')
                ->all();
        }

        return Auth::user()
            ->markets()
            ->orderBy('created_at')
            ->get()
            ->lists('name', 'id')
            ->all();
    }
    
    /**
     * Get list of all markets
     *
     * @return mixed
     */
    public static function getMarketsListOrderByCreate()
    {
        if (Auth::user()->isAdmin()) {
            return Market::orderBy('created_at', 'DESC')
                ->lists('name', 'id')
                ->all();
        }

        return Auth::user()
            ->markets()
            ->orderBy('created_at', 'DESC')
            ->get()
            ->lists('name', 'id')
            ->all();
    }
     
    /**
     * Get list of available market languages
     *
     * @return mixed
     */
    public static function getUserLanguagesList()
    {
        if (Auth::user()->isAdmin()) {
            return Market::lists('languages_id')
                ->all();
        }

        return Auth::user()
            ->markets()
            ->get()
            ->lists('languages_id')
            ->all();
    }

    /**
     * Get the market settings for the given market
     *
     * @return $this
     */
    public function marketSettings()
    {
        return $this->belongsToMany('App\Models\MarketSetting', 'market_setting_market')
            ->withTimestamps()
            ->withPivot('value');
    }

    /**
     * Relation markets -> languages
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function marketsLanguages()
    {
        return $this->belongsTo('App\Models\Language', 'languages_id');
    }

    /**
     * Get default locale for the market
     * @return mixed
     */
    public static function getDefaultLocale()
    {
        return Language::where('abbr', '=', strtoupper(env('DEFAULT_LANGUAGE')))->first();
    }

    /**
     * Get default market
     * @return Model|null|static
     */
    public static function getDefaultMarket()
    {
        return Market::where('languages_id', '=', Market::getDefaultLocale()->id)->first();
    }

    /**
     * Get the users with the market
     *
     * @return $this
     */
    public function users()
    {
        return $this->belongsToMany('App\Models\User')
            ->withTimestamps()
            ->withPivot('user_id');
    }

    /**
     * Get market by Id
     *
     * @param int $marketId
     * @return type
     */
    public static function getMarketById($marketId)
    {
        return self::with('marketsLanguages')
            ->where('id', '=', $marketId)
            ->first();
    }
    
    /**
     *
     * @param int $marketId
     * @return type
     */
    public static function getMarketUsers($marketId)
    {
        return Market::with([
            'users' => function ($query) {
                $query->join('roles', 'roles.id', '=', 'users.role_id')
                    ->where('roles.name', '=', Role::MEMBER_NAME);
            },
            'marketUser' => function ($query) {
                $query->join('users', 'users.id', '=', 'market_user.user_id')
                    ->join('roles', 'roles.id', '=', 'users.role_id')
                    ->where('roles.name', '=', Role::COACH_NAME);
            }])->where('id', '=', $marketId)->first();
    }
}
