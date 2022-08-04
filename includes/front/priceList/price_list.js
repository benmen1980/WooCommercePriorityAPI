jQuery(document).ready(function ($) {
    $(document).on('change', '.input-text.qty.text', function (event) {
        // shop- wait for Sunli to fix his bug T20
        // cart and product
        let tr = $(event.target).parent().parent().parent().find('#price_list')[0].rows
        let step = $(event.target).first().val()
        let price;
        for (let i = 1; i < tr.length; i++) {
            let td = tr[i].getElementsByTagName("td")
            let td1 = tr[i - 1].getElementsByTagName("td")
            if (parseInt(step) < parseInt(td[1].textContent)) {
                price = td1[0].textContent
                $(event.target).parent().parent().parent().find('.price').text('₪' + price)
                 $(event.target).parent().parent().parent().find('#realprice').val(price)
            } else if (step >= td[1].textContent) {
                price = td[0].textContent
                $(event.target).parent().parent().parent().find('.price').text('₪' + price)
                $(event.target).parent().parent().parent().find('#realprice').val(price)
            }
        }
    })
});
