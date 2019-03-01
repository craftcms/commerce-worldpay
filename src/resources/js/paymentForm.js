function initWorldpay() {
    // Because this might get executed before Worldpay is loaded.
    if (typeof Worldpay === "undefined") {
        setTimeout(initWorldpay, 200);
    } else {
        var $wrapper = $('.worldpay-form');
        var key = $wrapper.data('clientkey');
        var $form = $wrapper.parents('form');

        $form.on('submit', function (ev) {
            if ($(ev.currentTarget).find('input[name=worldpayToken]').length === 0)
            {
                ev.preventDefault();
                Worldpay.submitTemplateForm();
                return false;
            }
        });

        Worldpay.useTemplateForm({
            clientKey: key,
            form:'paymentForm',
            paymentSection:'payment-section',
            display:'inline',
            reusable:true,
            saveButton: false,
            callback: function(obj) {
                if (obj && obj.token) {
                    var _el = document.createElement('input');
                    _el.value = obj.token;
                    _el.type = 'hidden';
                    _el.name = 'worldpayToken';
                    document.getElementById($form.attr('id')).appendChild(_el);
                    document.getElementById($form.attr('id')).submit();
                }
            }
        });

        if ($('.modal').data('modal')) {
            $('.modal').data('modal').updateSizeAndPosition();
        }
    }
}

initWorldpay();