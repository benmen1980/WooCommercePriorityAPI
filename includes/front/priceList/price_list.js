
jQuery(document).ready(function ($) {
    setInitialPriceOnQtyOne();
    $(document).on('keyup change keydown keypress oninput', '.input-text.qty.text', function (event) {
        let tr = document.getElementById('simply-tire-price-grid-rows').rows;
        let qty = $(event.target).first().val();
        let i;
        let currencySymbol = document.querySelector('.woocommerce-Price-currencySymbol');
        let priceSpan = document.querySelector('.woocommerce-Price-amount');
        let priceSpanBdi = priceSpan.querySelector('bdi');
        let priceSpanBdiSpan = priceSpanBdi.querySelector('.woocommerce-Price-currencySymbol');
        let price =  priceSpanBdi.childNodes[1].textContent ;
        for (i = 0; i < tr.length ; i++) {
            let td1;
            let td = tr[i].querySelector('.simply-tire-price')
            if(i == tr.length -1){
                price = tr[i].querySelector('.simply-tire-price').querySelectorAll('span')[0].textContent
                priceSpanBdi.childNodes[0].textContent = price;
                document.getElementById('realprice').value = price
                break;
            }else{
                 td1 = tr[i + 1].querySelector('.simply-tire-quantity');
            }
            if (parseInt(qty) < parseInt(tr[i+1].querySelector('.simply-tire-quantity').textContent)) {
                price = tr[i].querySelector('.simply-tire-price').querySelectorAll('span')[0].textContent
                let  realprice = tr[i].querySelector('.simply-tire-price').querySelectorAll('span')[1].textContent
                priceSpanBdi.childNodes[0].textContent = price;
                document.getElementById('realprice').value = realprice
                break;
            }
        }
        if (price == undefined) {
            if (i > 0 || parseInt(tr[i].querySelector('.simply-tire-quantity').textContent) <= parseInt(step)) {
                price = tr[i].querySelector('.simply-tire-price').querySelectorAll('span')[1].textContent

            } else {
                price = document.getElementById('price_regular').value;
            }
           // document.getElementsByClassName('woocommerce-Price-amount')[0].textContent = ('₪' + price)
            document.getElementById('realprice').value = price
        }

    })
});
function setInitialPriceOnQtyOne(){
    let initQty = document.getElementById('simply-tire-price-grid-rows').querySelectorAll('tr')[0].querySelectorAll('td')[0].textContent;
    let price   = document.getElementById('simply-tire-price-grid-rows').querySelectorAll('tr')[0].querySelectorAll('td')[1].querySelectorAll('span')[0].textContent
    if(1 == initQty){
        let priceSpan = document.querySelector('.woocommerce-Price-amount');
        let priceSpanBdi = priceSpan.querySelector('bdi');
        priceSpanBdi.childNodes[0].textContent = price;

    }
}

