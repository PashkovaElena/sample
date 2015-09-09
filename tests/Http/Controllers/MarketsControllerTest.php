<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Market;
use App\Models\Language;
use App\Models\MarketUser;
use App\Models\MarketSetting;
use App\Models\MarketSettingMarket;
use Laracasts\TestDummy\Factory;
use App\DbTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class MarketsControllerTest
 * @package App\Http\Controllers
 */
class MarketsControllerTest extends DbTestCase
{
    protected $user;

    public function setUp()
    {
        parent::setUp();

        $this->user = factory(User::class)->make([
            'role_id' => Role::ADMIN_ID
        ]);
    }

    /** @test */
    public function testItDisplaysMarketsPage()
    {
        $this->be($this->user);

        // Run test
        $this->call('get', '/markets');

        $this->assertResponseOk();
        $this->assertViewHas('markets');
    }

    /** @test */
    public function testItDisplaysMarketsPageWithSettings()
    {
        $this->be($this->user);

        /* Get locale setting ID */
        $localeSettingId = MarketSetting::where('name', 'locale')->get()->first()->id;
        $this->assertNotEmpty($localeSettingId);

        $locale = Language::orderBy('id')->first();

        /* Create Market */
        $market = factory(Market::class)->create([
            'languages_id' => $locale->id
        ]);

        /* Create MarketSettingMarket */
        $marketSettingMarketArray = factory(MarketSettingMarket::class)->create([
            'market_id' => $market->id,
            'market_setting_id' => $localeSettingId,
            'value' => $locale->id
        ])->toArray();
        $this->assertNotEmpty($marketSettingMarketArray);

        // Run test
        $this->call('get', '/markets');

        $this->assertResponseOk();
        $this->assertViewHas('markets');
        $this->assertViewHas('marketsSettings');
        
        $html = new Crawler($this->response->getContent());
        $this->assertEquals(1, $html->filter('#edit-market-'.$market->id)->count());
        $this->assertEquals(
            $locale->id,
            $html->filter('#edit-market-'.$market->id. ' select[name="languages_id"] option:selected')->attr('value')
        );
    }

    /** @test */
    public function testItUpdatesMarket()
    {
        $this->be($this->user);

        // create a market
        $market = factory(Market::class)->create();
        $this->assertNotEmpty($market);

        // get market settings
        for ($i=0; $i<5; $i++) {
            $settings[] = factory(MarketSetting::class)->create();
        }
        $this->assertNotEmpty($settings);

        // add settings for the new market
        $marketSettingMarket = [];
        $settingsNum = 5;
        for ($i = 0; $i < $settingsNum; $i++) {
            $marketSettingMarket[$i] = factory(MarketSettingMarket::class)->make([
                'market_id' => $market->id
            ]);
        }

        $language = Language::first();
        // Create request parameters
        $testName = 'TestName';
        $currency = 'euro';
        $requestParams = [
            'tax_rate' => '22',
            'locale' => 'es',
            'currency' => $currency,
            'currency_symbol' => '€',
            'prefix' => 'none'
        ];

        // Run test
        $this->call('patch', '/markets/' . $market->id, [
            'settings' => $requestParams,
            'name' => $testName,
            'languages_id' => $language->id
        ]);

        $this->assertViewHas('market');

        $html = new Crawler($this->response->getContent());

        $this->assertEquals(1, $html->filter('input[name="settings[currency]"]')->count());

        $this->assertEquals($currency, $html->filter('input[name="settings[currency]"]')->attr('value'));
        $this->assertEquals($market->id, $html->filter('input[name="id"]')->attr('value'));

        //check that name is changed to the new one
        $this->assertEquals(Market::find($market->id)->name, $testName);

        //check that market settings are updated
        $newMarketSettings = MarketSettingMarket::where('market_id', $market->id)->get()->toArray();

        for ($i = 0; $i < $settingsNum; $i++) {
            $marketSettingName = MarketSetting::where('id', $newMarketSettings[$i]['market_setting_id'])
                ->first()
                ->name;

            foreach ($requestParams as $key => $val) {
                if ($key === $marketSettingName) {
                    $this->assertEquals($val, $newMarketSettings[$i]['value']);
                }
            }
        }
    }

    /** @test */
    public function testItUpdatesMarketWithInvalidTaxRate()
    {
        $this->be($this->user);

        // create a market
        $market = factory(Market::class)->create();
        $this->assertNotEmpty($market);

        // get market settings
        for ($i=0; $i<5; $i++) {
            $settings[] = factory(MarketSetting::class)->create([
                'name' => substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10)
            ]);
        }
        $this->assertNotEmpty($settings);

        // add settings for the new market
        $marketSettingMarket = [];
        $settingsNum = 5;
        for ($i = 0; $i < $settingsNum; $i++) {
            $marketSettingMarket[$i] = factory(MarketSettingMarket::class)->create([
                'market_id' => $market->id,
                'market_setting_id' => $settings[$i]->id
            ]);
        }

        $language = Language::first();
        // Create request parameters
        $testName = 'TestName';
        $currency = 'euro';
        $requestParams = [
            // Invalid tax rate
            'tax_rate' => '22eeee',
            'locale' => 'es',
            'currency' => $currency,
            'currency_symbol' => '€',
            'prefix' => 'none'
        ];

        // Run test
        $this->call('patch', '/markets/' . $market->id, [
            'settings' => $requestParams,
            'name' => $testName,
            'languages_id' => $language->id
        ]);

        $this->assertSessionHasErrors(['settings.tax_rate']);
    }

    /** @test */
    public function testItFailsOnUpdateMarketIfModelNotFound()
    {
        $this->be($this->user);
        
        $requestParams = [
            'tax_rate' => '22',
            'locale' => 'es',
            'currency' => 'euro',
            'currency_symbol' => '€',
            'prefix' => 'none'
        ];
        
        // Run test
        $this->call('patch', '/markets/1234568789', [
            'settings' => $requestParams,
            'name' => 'TestName',
            'languages_id' => Language::first()->id
        ]);
        
        $this->assertJson($this->response->getContent());

        $decodedResponseContent = json_decode($this->response->getContent(), true);
        $this->assertEquals(trans('general.not_found'), $decodedResponseContent['message']);
        $this->assertResponseStatus(\Illuminate\Http\Response::HTTP_NOT_FOUND);
    }
    
    /** @test */
    public function testItFailsOnDeleteMarketIfModelNotFound()
    {
        $this->be($this->user);
                
        // Run test
        $this->call('delete', 'markets/delete/1234568789', [
            '_token' => csrf_token()
        ]);
        
        $this->assertJson($this->response->getContent());

        $decodedResponseContent = json_decode($this->response->getContent(), true);
        $this->assertEquals(trans('general.not_found'), $decodedResponseContent['message']);
        $this->assertResponseStatus(\Illuminate\Http\Response::HTTP_NOT_FOUND);
    }
    
    /** @test */
    public function testItUpdatesMarketWithInvalidTaxRateTooBig()
    {
        $this->be($this->user);

        // create a market
        $market = factory(Market::class)->create();
        $this->assertNotEmpty($market);

        // get market settings
        $settings = factory(MarketSetting::class, 5)->make([
                'name' => substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10)
            ]);
        $this->assertNotEmpty($settings);

        // add settings for the new market
        $marketSettingMarket = [];
        $settingsNum = 5;
        for ($i = 0; $i < $settingsNum; $i++) {
            $marketSettingMarket[$i] = factory(MarketSettingMarket::class, [
                'market_id' => $market->id
            ]);
        }

        $language = Language::first();
        // Create request parameters
        $testName = 'TestName';
        $currency = 'euro';
        $requestParams = [
            // Invalid tax rate
            'tax_rate' => '101',
            'locale' => 'es',
            'currency' => $currency,
            'currency_symbol' => '€',
            'prefix' => 'none'
        ];

        // Run test
        $resp = $this->call('patch', '/markets/' . $market->id, [
            'settings' => $requestParams,
            'name' => $testName,
            'languages_id' => $language->id
        ]);

        $this->assertSessionHasErrors(['settings.tax_rate']);
    }

    /** @test */
    public function testItFailsOnWrongValidations()
    {
        $this->be($this->user);

        $testName = 'Test  NameTest  NameTest  NameTest  NameTest  NameTest  Name';

        $market = factory(Market::class)->create();
        $this->assertNotEmpty($market);

        for ($i=0; $i<5; $i++) {
            $settings[] = factory(MarketSetting::class)->create([
                'name' => substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10)
            ]);
        }
        $this->assertNotEmpty($settings);

        $language = Language::first();
        $requestParams = [];
        foreach ($settings as $setting) {
            $requestParams[$setting->name] = factory(MarketSettingMarket::class)->make()->value;
        }

        // Run test
        $this->call('patch', 'markets/' . $market->id, [
            'settings' => $requestParams,
            'name' => $testName,
            'languages_id' => $language->id,
            '_token' => csrf_token()
        ]);

        $this->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function testItStoresNewMarket()
    {
        $this->be($this->user);

        $languages = Language::first()->toArray();

        $market = factory(Market::class)->make([
            'name' => 'New Market',
            'languages_id' => $languages['id'],
            'created_at' => null,
            'updated_at' => null
        ]);

        $settings = factory(MarketSetting::class, 5)->make([
                'name' => substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10)
            ]);
        $this->assertNotEmpty($settings);

        // Create request parameters
        $requestParams = [];
        $requestParams['tax_rate'] = '10';
        $requestParams['currency'] = 'usd';
        $requestParams['currency_symbol'] = '$';
        $requestParams['prefix'] = 'none';

        // Run test
        $this->call('post', '/markets/store', [
            'settings' => $requestParams,
            'name' => $market->name,
            'languages_id' => $market->languages_id,
            '_token' => csrf_token()
        ]);

        // check that market is created successfully
        $createdMarket = Market::where('name', 'New Market')->get()->first();
        $this->assertNotEmpty($createdMarket);

        // check that market settings were stored successfully
        $newMarketSettings = MarketSettingMarket::where('market_id', $createdMarket->id)
            ->get()
            ->toArray();

        $settingsNum = 3;
        for ($i = 0; $i < $settingsNum; $i++) {
            $marketSettingName = MarketSetting::where('id', $newMarketSettings[$i]['market_setting_id'])
                ->first()
                ->name;

            foreach ($requestParams as $key => $val) {
                if ($key === $marketSettingName) {
                    $this->assertEquals($val, $newMarketSettings[$i]['value']);
                }
            }
        }
    }

    /** @test */
    public function testItStoresNewMarketWithInvalidTaxRate()
    {
        $this->be($this->user);

        $languages = Language::first()->toArray();

        $market = factory(Market::class)->make([
            'name' => 'New Market',
            'languages_id' => $languages['id'],
            'created_at' => null,
            'updated_at' => null,
        ]);

        $settings = factory(MarketSetting::class, 5)->make([
                'name' => substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10)
            ]);
        $this->assertNotEmpty($settings);

        // Create request parameters
        $requestParams = [];

        // Tax rate is invalid
        $requestParams['tax_rate'] = '10eeee';

        $requestParams['currency'] = 'usd';
        $requestParams['currency_symbol'] = '$';
        $requestParams['prefix'] = 'none';

        // Run test
        $this->call('post', '/markets/store', [
            'settings' => $requestParams,
            'name' => $market->name,
            'languages_id' => $market->languages_id,
            '_token' => csrf_token()
        ]);

        $this->assertSessionHasErrors(['settings.tax_rate']);

        // check that market is created successfully
        $createdMarket = Market::where('name', 'New Market')->get()->first();
        $this->assertEmpty($createdMarket);
    }

    /** @test */
    public function testItStoresNewMarketWithInvalidTaxRateTooBig()
    {
        $this->be($this->user);

        $languages = Language::first()->toArray();

        $market = factory(Market::class)->make([
            'name' => 'New Market',
            'languages_id' => $languages['id'],
            'created_at' => null,
            'updated_at' => null,
        ]);

        $settings = factory(MarketSetting::class, 5)->make([
                'name' => substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10)
            ]);
        $this->assertNotEmpty($settings);

        // Create request parameters
        $requestParams = [];

        // Tax rate is invalid
        $requestParams['tax_rate'] = '101';

        $requestParams['currency'] = 'usd';
        $requestParams['currency_symbol'] = '$';
        $requestParams['prefix'] = 'none';

        // Run test
        $this->call('post', '/markets/store', [
            'settings' => $requestParams,
            'name' => $market->name,
            'languages_id' => $market->languages_id,
            '_token' => csrf_token()
        ]);

        $this->assertSessionHasErrors(['settings.tax_rate']);

        // check that market is created successfully
        $createdMarket = Market::where('name', 'New Market')->get()->first();
        $this->assertEmpty($createdMarket);
    }

    /** @test */
    public function testItFailsWhenStoresNewMarketWithoutName()
    {
        $this->be($this->user);

        $marketAttributes['name'] = '';
        $marketAttributes['settings'] = [
            'locale' => 'en',
            'tax_rate' => '22',
            'prefix' => 'none',
            'currency' => 'uah'
        ];
        $marketAttributes['_token'] = csrf_token();

        // Run test
        $this->call('post', '/markets/store', $marketAttributes);

        $this->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function testItDeletesMarket()
    {
        $this->be($this->user);

        $market = new Market();
        $market->name = 'Test';
        $market->created_at = null;
        $market->updated_at = null;
        $market->save();

        // Run test
        $response = $this->call('delete', 'markets/delete/' . $market->id, [
            'marketId' => $market->id,
            'name' => $market->name,
            '_token' => csrf_token()
        ]);

        $this->assertEmpty(Market::where('id', $market->id)->get()->first());
        
        $this->assertJson($response->getContent());
        
        $content = json_decode($response->getContent(), true);
        
        // check users were reassigned to the market2
        $this->assertEquals(0, $content['coachesNum']);
        $this->assertEquals(0, $content['customersNum']);
    }
    
    /** @test */
    public function testItDisplays200WhenAuthenticatedAsAdmin()
    {
        $this->be($this->user);
        $this->action('get', 'MarketsController@index');

        $this->assertResponseOk();
    }

    /** @test */
    public function testItRedirectToLoginWhenAuthenticatedAsCoachAndOpenMarketsPage()
    {
        $this->loggedAsCoachUser();

        $this->action('get', 'MarketsController@index');

        $this->assertRedirectedTo('auth/login');
    }

    /** @test */
    public function testItRedirectToLoginWhenAuthenticatedAsMemberAndOpenMarketsPage()
    {
        $this->loggedAsMemberUser();

        $this->action('get', 'MarketsController@index');

        $this->assertRedirectedTo('auth/login');
    }
    
    /** @test */
    public function testItDeletesMarketWithCustomers()
    {
        $this->be($this->user);

        $market1 = factory(Market::class)->create([
            'name' => 'Test'
        ]);
        $market2 = factory(Market::class)->create([
            'name' => 'Test 2'
        ]);
        
        $coach = factory(User::class)->create([
            'role_id' => Role::COACH_ID
        ]);
        
        $customer = factory(User::class)->create([
            'role_id' => Role::MEMBER_ID
        ]);
        
        $marketUser1 = factory(MarketUser::class, 'market_user')->create([
            'user_id' => $coach->id,
            'market_id' => $market1->id
        ]);
        
        $marketUser2 = factory(MarketUser::class, 'market_user')->create([
            'user_id' => $customer->id,
            'market_id' => $market1->id
        ]);
        
        // Run test
        $response = $this->call('delete', 'markets/delete/' . $market1->id . '/'. $market2->id, [
            '_token' => csrf_token()
        ]);

        $this->assertResponseOk();
        $this->assertJson($response->getContent());
        
        $content = json_decode($response->getContent(), true);
        
        // check users were reassigned to the market2
        $this->assertEquals(1, $content['coachesNum']);
        $this->assertEquals(1, $content['customersNum']);
        
        // check market1 was deleted
        $this->assertEmpty(Market::where('id', $market1->id)->get()->first());
    }
}
