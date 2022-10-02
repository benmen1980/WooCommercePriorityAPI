jQuery(document).ready(function ($) {

    $(document).on('keyup change keydown keypress oninput', '.input-text.qty.text', function (event) {
        let tr = document.getElementById('price_list').rows
        let step = $(event.target).first().val()
        let price, i
        for (i = 0; i < tr.length - 1; i++) {
            let td = tr[i].getElementsByTagName("td")
            let td1 = tr[i + 1].getElementsByTagName("td")
            if (parseInt(step) < parseInt(td1[1].textContent)) {
                price = td[0].textContent
                document.getElementsByClassName('woocommerce-Price-amount')[0].textContent = ('₪' + price)
                document.getElementById('realprice').value = price
                break;
            }
        }
        if (price == undefined) {
            let td = tr[i].getElementsByTagName("td")
            if (i > 0 || parseInt(td[1].textContent) <= parseInt(step)) {
                price = td[0].textContent

            } else {
                price = document.getElementById('price_regular').value;
            }
            document.getElementsByClassName('woocommerce-Price-amount')[0].textContent = ('₪' + price)
            document.getElementById('realprice').value = price
        }

    })
});

