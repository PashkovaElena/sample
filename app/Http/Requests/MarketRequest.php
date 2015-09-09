<?php

namespace App\Http\Requests;

use Input;
use App\Models\Market;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class MarketRequest
 * @package App\Http\Requests
 */
class MarketRequest extends FormRequest
{
    /**
     * Method to add custom request validators
     */
    public function addCustomValidators()
    {
        $factory = $this->container->make('Illuminate\Validation\Factory');

        $factory->extend(
            'market_name',
            function ($attribute, $value, $parameters) {
                $isNameExist = Market::where('id', '<>', $parameters[1])
                    ->where('name', '=', trim($value))
                    ->count();
                return $isNameExist ? false : true;
            },
            trans('markets.validation_name_unique')
        );
    }

    /**
     * Get the validation rules that apply to the request
     *
     * @return array
     */
    public function rules()
    {
        if (self::isMethod('delete')) {
            return [];
        }

        $this->addCustomValidators();

        $input = $this->request->get('name');
        $marketId = $this->request->get('id');

        $this->request->set('name', trim(Input::get('name')));

        return [
            'name' => 'required|unique:markets,name,' . $this->get('id') . ',id|market_name:' . $input . ',' . $marketId
                . '|min:1|max:50',
            'languages_id' => 'required',
            'settings.currency' => 'max:25',
            'settings.currency_symbol' => 'max:25',
            'settings.tax_rate' => 'numeric|max:100',
        ];
    }

    /**
     * Custom validation messages
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.unique' => trans('markets.validation_name_unique'),
            'languages_id.required' => trans('markets.lang_required'),
            'settings.tax_rate.regex' => trans('markets.taxformat_invalid'),
            'settings.tax_rate.numeric' => trans('markets.taxformat_invalid'),
            'settings.tax_rate.max' => trans('markets.taxformat_max'),
            'name.max' => trans('general.validation_name_max'),
            'settings.currency.max' => trans('markets.validation_currency_max25'),
            'settings.currency_symbol.max' => trans('markets.validation_currency_symbol_max25')
        ];
    }

    /**
     * Determine if the user is authorized to make this request
     *
     * @return bool
     */
    public function authorize()
    {
        return \Auth::check();
    }
}
