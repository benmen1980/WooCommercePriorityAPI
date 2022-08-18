jQuery(document).ready(function ($) {

    $(document).on('keyup change', '.input-text.qty.text', function (event) {
        // shop- wait for Sunli to fix his bug T20
        // cart and product
        let tr = document.getElementById('price_list').rows
        let step = $(event.target).first().val()
        let price
        //let quant = 1
        // let price_all = 0
        //  = $(event.target).parent().parent().parent().find('.price').text()
        for (let i = 1; i < tr.length; i++) {
            let td = tr[i].getElementsByTagName("td")
            let td1 = tr[i - 1].getElementsByTagName("td")
            if (parseInt(step) < parseInt(td[1].textContent)) {
                price = td1[0].textContent
                document.getElementsByClassName('woocommerce-Price-amount')[0].textContent = ('₪' + price)
                document.getElementById('realprice').value = price
                // quant = td[1].textContent
            } else if (step >= td[1].textContent) {
                price = td[0].textContent
                document.getElementsByClassName('woocommerce-Price-amount')[0].textContent = ('₪' + price)
                document.getElementById('realprice').value = price
            }
        }

    })
});

