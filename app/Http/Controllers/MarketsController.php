<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Market;
use App\Models\Language;
use App\Models\MarketSetting;
use App\Models\MarketSettingMarket;
use App\Http\Requests\Request as HttpRequest;
use App\Http\Requests\CreateMarketRequest;
use Request;
use App\Http\Requests\MarketRequest;
use Illuminate\Support\Facades\Route;
use App\Models\MarketUser;
use Illuminate\Http\Response as BaseResponse;

/**
 * Class MarketsController
 * @package App\Http\Controllers
 */
class MarketsController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('admin');
    }

    /**
     * Show the list of markets
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $languages = Language::getLanguages();
        $languagesForCreate = ['' => ''] + Language::getLanguages(); //empty first value for the chosen jquery plugin
        $languagesVsLocales = json_encode(Language::getLanguagesAndLocales());
        $defaultLocale = Market::getDefaultLocale()->locale;
        $markets = Market::getMarketsPagination();
        $marketList = ['' => ''] + Market::getMarketsListOrderByCreate();

        $marketsSettings = [];

        foreach ($markets as $market) {
            $marketsSettings[$market->id]['id'] = $market->id;
            foreach ($market->marketSettings as $marketSetting) {
                $marketsSettings[$market->id][$marketSetting['name']] = $marketSetting->pivot->value;
            }
        }

        return view('markets.index', compact(
            'markets',
            'marketsSettings',
            'languages',
            'languagesForCreate',
            'languagesVsLocales',
            'defaultLocale',
            'marketList'
        ));
    }

    /**
     * Store the new market
     *
     * @param MarketRequest $request
     * @return string
     */
    public function store(MarketRequest $request)
    {
        $newMarket = Market::create([
            'name' => $request['name'],
            'languages_id' => $request['languages_id']
        ]);
        $data = [];
        $params = $request->all();

        foreach ($params['settings'] as $paramKey => $paramsVal) {
            $marketSetting = MarketSetting::where('name', $paramKey)->first();

            if (isset($marketSetting) && !empty($paramsVal)) {
                $data[$marketSetting->id] = [
                    'market_setting_id' => $marketSetting->id,
                    'value' => $paramsVal
                ];
            }
        }
        $newMarket->marketSettings()->attach($data);

        $markets = Market::getMarketsPagination();
        $languages = Language::getLanguages();
        $languagesVsLocales = json_encode(Language::getLanguagesAndLocales());
        
        $marketsSettings = [];

        foreach ($markets as $market) {
            $marketsSettings[$market->id]['id'] = $market->id;
            if (isset($market->marketSettings) && count($market->marketSettings) > 0) {
                foreach ($market->marketSettings as $marketSetting) {
                    $marketsSettings[$market->id][$marketSetting['name']] = $marketSetting->pivot->value;
                }
            }
        }
        
        return view('markets.list-markets', compact(
            'markets',
            'languages',
            'languagesVsLocales',
            'marketsSettings'
        ))->render();
    }

    /**
     * Update the market
     *
     * @param MarketRequest $request
     * @param $id
     * @return \Illuminate\View\View
     */
    public function update(MarketRequest $request, $id)
    {
        $params = $request->all();
        
        $market = Market::findOrFail($id);
        
        $market->update($params);

        if (isset($params['settings'])) {
            foreach ($params['settings'] as $paramKey => $paramsVal) {
                $marketSetting = MarketSetting::where('name', $paramKey)->first();

                MarketSettingMarket::updateOrCreate(
                    [
                        'market_id' => $id,
                        'market_setting_id' => $marketSetting['id']
                    ],
                    [
                        'market_id' => $id,
                        'market_setting_id' => $marketSetting['id'],
                        'value' => $paramsVal
                    ]
                );
            }
        }

        $languages = Language::getLanguages();
        $languagesVsLocales = json_encode(Language::getLanguagesAndLocales());
        $defaultLocale = Market::getDefaultLocale()->locale;
        $currentLocale = isset($market->marketsLanguages->locale)
            ? $market->marketsLanguages->locale
            : $defaultLocale;
        $name = $market->name;
        
        $marketId = $market->id;
        $mSettings = Market::with([
            'marketSettings' => function ($q) use ($marketId) {
                $q->where('market_id', $marketId);
            }
        ])
            ->get()
            ->toArray();

        $marketsSettings = [];
        foreach ($mSettings as $setting) {
            if (!empty($setting['market_settings'])) {
                $marketsSettings['id'] = $marketId;
                foreach ($setting['market_settings'] as $set) {
                    $marketsSettings[$set['name']] = $set['pivot']['value'];
                }
            }
        }

        return view('markets.edit', compact(
            'market',
            'marketId',
            'name',
            'marketsSettings',
            'languages',
            'languagesVsLocales',
            'defaultLocale',
            'currentLocale'
        ));
    }

    /**
     * Delete the market, reassign existed customers and coachers to the new market
     *
     * @param $id
     * @param int|null $newMarketId
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id, $newMarketId = null)
    {
        $coachesNum = 0;
        $customersNum = 0;
        
        // reassign existed customers and coachers to the new market
        if (!empty($newMarketId)) {
            MarketUser::reassignUsers($id, $newMarketId);
            
            $markets = Market::getMarketUsers($newMarketId);
            $coachesNum = $markets->marketUser->count();
            $customersNum = $markets->users->count();
        }
        
        $res = Market::findOrFail($id)->delete();
        
        return response()->json([
            'status' => $res,
            'coachesNum'  => $coachesNum,
            'customersNum' => $customersNum
        ]);
    }
}
