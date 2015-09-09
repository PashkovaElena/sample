(function($, window) {
    window.Markets = {
        init: function() {
            var text = $('#first-line').text(),
                markets = $('.dropdownMarkets  option');

            $(window.document)
                .on('click', '.btnCreateMarket', this.createMarket)
                .on('click', '.btnEditMarket', this.editMarket)
                .on('click', '.btnDeleteMarket', this.deleteMarket)
                .on('click', '.btnCancelMarket', this.cancelMarket)
                .on('click', '#listMarkets tr[data-toggle="collapse"]', collapseRow)
                .on('change', 'input, select', markChanged)
                .on('click', '.market-delete', function() {
                    $('input[name="marketId"]').val($(this).data('market-id'));
                    $('input[name="name"]').val($(this).data('market-name'));
                })
                .on('change', '.dropdownLang', function() {
                    var locales = $('#languagesVsLocales').val(),
                        selectedLang = $('option:selected', this).text(),
                        localesArr = $.parseJSON(locales),
                        cnt = localesArr.length,
                        i;

                    if ($(this).siblings('.localeValue, .lblLocale').hasClass('hide')) {
                        $(this).siblings('.localeValue, .lblLocale').removeClass('hide');
                    }

                    for (i = 0; i < cnt; i++) {
                        if (localesArr[i].name === selectedLang) {
                            $(this).siblings('div.localeValue').text(localesArr[i].locale);
                        }
                    }
                })
                .on('click', '#btnNewMarket', this.clearForm);

            $('.dropdownLang').change(this.changeLang);
            window.General.initChosen($('select.dropdownLang'));
            window.General.initChosen($('select.dropdownMarkets'));
            window.General.initInfiniteScroll(window.General.getInfiniteScrollSelector());

            $('[name="settings[tax_rate]"]').autoNumeric('init', {
                aSep: ' ',
                aDec: '.',
                vMax: '100.00'
            });

            $('#selectNewMarket').on('hide.bs.modal', function() {
                $.each(markets, function(key, item) {
                    $(item).show();
                });

                $('select.dropdownMarkets').val('').trigger('chosen:updated');
                $(this).find('.alert').addClass('hide');
                setTimeout(function() {
                    $('#first-line').text(text);
                }, 500);
            });
        },
        createMarket: function(e) {
            var $form = $(this).parents('form'),
                $errors = $form.prev(),
                $row,
                rowId,
                newName;

            e.preventDefault();
            $('.btnCreateMarket, .btnCancelMarket').button('loading');

            $.ajax({
                url: $form.attr('action'),
                data: $form.serializeArray(),
                type: $form.attr('method'),
                error: function(xhr) {
                    window.General.convertErrorsToList($errors, xhr.responseText);
                },
                success: function(responce) {
                    $('.modal').modal('hide');
                    window.Markets.displayMsg($('.alertSavedSuccess'));
                    $('.btnCreateMarket, .btnCancelMarket').button('reset');
                    window.General.destroyInfiniteScroll(window.General.getInfiniteScrollSelector());
                    $('.listItems').html(responce);

                    $row = $('#listMarkets tr').eq(1);
                    rowId = $row.attr('id');
                    newName = $row.find('td:first').text();

                    $('select.dropdownMarkets')
                        .prepend($('<option></option>')
                            .attr('value', rowId)
                            .text(newName));

                    $('select.dropdownMarkets').val('').trigger('chosen:updated');
                    window.General.initChosen($('select.dropdownLang'));
                    window.General.initInfiniteScroll(window.General.getInfiniteScrollSelector());
                }
            });
        },
        editMarket: function(e) {
            var $form = $(this).parents('form'),
                $errors = $form.prev(),
                rowId = $(this).parents().prev('tr').attr('id'),
                trTarget = $(this).parents().prev('tr').data('target');

            e.preventDefault();

            $.ajax({
                url: $form.attr('action'),
                data: $form.serializeArray(),
                type: $form.attr('method'),
                success: function(response) {
                    var newName = $form.find('[name="name"]').val();

                    $(trTarget).collapse('hide');
                    $('#' + rowId).find('td.market-name').html(newName);
                    window.Markets.displayMsg($('.alertEditedSuccess'));
                    $form.parents('td').html(response);

                    $('.dropdownMarkets [value="' + rowId + '"]').text(newName);
                    $('select.dropdownMarkets').val('').trigger('chosen:updated');
                    window.General.initChosen($('select.dropdownLang'));

                    $('[name="settings[tax_rate]"]').autoNumeric('init', {
                        aSep: ' ',
                        aDec: '.',
                        vMax: '100.00'
                    });
                },
                error: function(xhr) {
                    window.General.convertErrorsToList($errors, xhr.responseText);
                }
            });
        },
        cancelMarket: function() {
            var $btn = $(this),
                $modal = $('#confirmAction');

            if ($(this).parents('form').hasClass('changed')) {
                $modal.find('.cancel').removeClass('hidden').siblings().addClass('hidden');
                $modal.modal('show');
            } else {
                $btn.parents('tr').collapse('hide');
                if ($('.market-create').length > 0) {
                    $('.market-create').modal('hide');
                }
            }

            if ($modal.hasClass('hide')) {
                $modal.removeClass('hide');
            }

            $('.btnActionConfirm').unbind('click').on('click', function() {
                var $form = $btn.parents('form');

                $form.removeClass('changed');
                $modal.modal('hide');
                if ($('.market-create').length > 0) {
                    $('.market-create').modal('hide');
                }
                $btn.parents('tr').collapse('hide');
                setTimeout(function() {
                        $form[0].reset();
                        $form.prev().find('ul').empty();
                        $form.prev().addClass('hide');
                    }, 1000
                );
            });
        },
        deleteMarket: function() {
            var $btn = $(this),
                url = $btn.data('action'),
                $trForm = $btn.parents('tr'),
                $errors = $trForm.find('.alert'),
                currMarketId = $btn.data('market-id'),
                coachesNum = $trForm.prev().find('td:nth-child(2)').text(),
                customersNum = $trForm.prev().find('td:nth-child(3)').text(),
                selectedMarketId = 0,
                $modal = $('#selectNewMarket');

            if (customersNum > 0 || coachesNum > 0) {
                // remove current market from the list
                $('.dropdownMarkets [value="' + currMarketId + '"]').hide();
                $('select.dropdownMarkets').val('').trigger('chosen:updated');

                // update text in the modal with the appropriate values
                // of customers and coaches number
                $('#first-line').text(format([customersNum, coachesNum],
                    $('#first-line').text()));

                $modal.modal('show');
            } else {
                $modal = $('#confirmAction');
                $modal.find('.delete').removeClass('hidden').siblings().addClass('hidden');
                $modal.modal('show');
            }
            if ($modal.hasClass('hide')) {
                $modal.removeClass('hide');
            }

            $('.btnActionConfirm').unbind('click').on('click', function() {
                var $errorsModal = $('#selectNewMarket').find('.alert'),
                    isReassign = $(this).parents('#selectNewMarket').length;

                if (isReassign) {
                    if ($('.dropdownMarkets :selected').val() === '') {
                        window.General.convertErrorsToList(
                            $errorsModal,
                            '{ "market":[ "' + $('input[name="errorMsg"]').val() + '"] }'
                        );
                        $errorsModal.removeClass('hide');
                        return false;
                    }

                    selectedMarketId = $('.dropdownMarkets option:selected').val();
                    url = $btn.data('action') + '/' + selectedMarketId;
                }

                $modal.modal('hide');

                $.ajax({
                    url: url,
                    type: 'delete',
                    data: {'_token': $btn.data('token')},
                    success: function(response) {
                        var idEditForm,
                            $msg;

                        if (response.status) {
                            idEditForm = $trForm.attr('id');
                            $msg = $('.alertSuccessDelete');

                            // if users were reassigned
                            if (isReassign) {
                                // update coaches num
                                $('#' + selectedMarketId).find('td:nth-child(2)').text(response.coachesNum);
                                // update customers num
                                $('#' + selectedMarketId).find('td:nth-child(3)').text(response.customersNum);
                                $msg = $('.alertMovedSuccess');
                            }

                            $('.dropdownMarkets [value="' + currMarketId + '"]').remove();
                            $('select.dropdownMarkets').val('').trigger('chosen:updated');

                            if ($trForm.length > 0) {
                                $('[data-target=#' + idEditForm + ']').remove();
                                $trForm.remove();
                            }
                            window.Markets.displayMsg($msg);
                        }
                    },
                    error: function(xhr) {
                        window.General.convertErrorsToList($errors, xhr.responseText);
                    }
                });
            });
        },
        clearForm: function() {
            $(this).parents().find('.alert-danger').addClass('hide');
            $('#formCreateMarket .localeValue, #formCreateMarket .lblLocale').addClass('hide');
            $('#formCreateMarket .chosen-select').val('').trigger('chosen:updated');
            $('form#formCreateMarket')[0].reset();
        },
        changeLang: function() {
            var locales = $('#languagesVsLocales').val(),
                selectedLang = $('option:selected', this).text(),
                localesArr = $.parseJSON(locales),
                cnt = localesArr.length,
                i;

            if ($(this).siblings('.localeValue, .lblLocale').hasClass('hide')) {
                $(this).siblings('.localeValue, .lblLocale').removeClass('hide');
            }

            for (i = 0; i < cnt; i++) {
                if (localesArr[i].name === selectedLang) {
                    $(this).siblings('div.localeValue').text(localesArr[i].locale);
                }
            }
        },
        displayMsg: function($msg) {
            $msg.removeClass('hide').addClass('in');
            setTimeout(function() {
                $msg.removeClass('in').addClass('hide');
            }, 2000);
        }
    };

    function collapseRow(e) {
        var id = $(this).data('target');
        if ($(id).hasClass('in')) {
            e.stopPropagation();
            $(id).find('.btnCancelMarket').trigger('click');
        }
    }

    function markChanged() {
        var $form = $(this).parents('form');
        if (!$form.hasClass('changed')) {
            $form.addClass('changed');
        }
    }

    function format(args, text) {
        var regex = new RegExp('{-?[0-9]+}', 'g');

        return text.replace(regex, function(item) {
            var intVal = parseInt(item.substring(1, item.length - 1), 10),
                replace;

            if (intVal >= 0) {
                replace = args[intVal];
            } else if (intVal === -1) {
                replace = '{';
            } else if (intVal === -2) {
                replace = '}';
            } else {
                replace = '';
            }
            return replace;
        });
    }

    $(function marketsInit() {
        window.Markets.init();
    });
}(window.$, window));
