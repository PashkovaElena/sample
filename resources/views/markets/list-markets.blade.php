<div class="listItems">
    @if (count($markets) > 0)
        <table id="listMarkets" class="table table-striped">
            <thead>
               <tr>
                   <th>{{ trans('markets.name' ) }}</th>
                   <th>{{ trans('markets.customers' ) }}</th>
               </tr>
            </thead>
            <tbody class="items">
            @if (count($markets) > 0)
            <?php $i = 0; ?>

            @foreach ($markets as $market)
                <?php $idPopup = "edit-market-{$market->id}";?>

                <tr data-toggle="collapse"
                    aria-expanded="false"
                    data-target="#{{ $idPopup }}"
                    class="item collapsed"
                    id="{{ $market->id }}">

           
                    <td class="market-name">{{ $market->name }}</td>
                    <td class="hidden">{{ $market->marketUser->count() }}</td>
                    <td>{{ $market->users->count() }}</td>
                </tr>
                <tr id="{{ $idPopup }}"
                    class="collapse item">
                    <td colspan="2">
                        @include('markets.edit', [
                            'marketId' => $market->id,
                            'name' => $market->name,
                            'currentLocale' => isset($market->marketsLanguages->locale) ? $market->marketsLanguages->locale : $defaultLocale,
                            'marketsSettings'=> isset($marketsSettings[$market->id])?$marketsSettings[$market->id]:''
                        ])
                    </td>
            @endforeach
            @endif
             </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">
                        @if (count($markets) > 0)
                        <?php echo $markets->render(); ?>
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>  
{!! Form::hidden('languagesVsLocales', $languagesVsLocales, array('id' => 'languagesVsLocales')) !!}
