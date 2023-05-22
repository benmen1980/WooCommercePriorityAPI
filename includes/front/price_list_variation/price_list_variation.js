// Update the product price in real time
console.log('price list variation loaded...');
(function($) {
    jQuery(document).ready(function() {
        // Attach an event listener to the variation form
        $('form.variations_form').on('found_variation', function(event, variation) {
            // Variation has changed, execute your custom JavaScript code here
            console.log('Variation changed:', variation);
            // Get all tables with data-variation-id attribute
            var tables = document.querySelectorAll('table[data-variation-id]');

// Loop through each table and add the hidden-table class
            tables.forEach(function(table) {
                table.classList.add('hidden-table');
            });
            var variationId = variation.variation_id;
            var selector = `table[data-variation-id="${variationId}"]`;
            var table = document.querySelector(selector);
// Toggle the hidden-table class
            if (table) {
                table.classList.toggle('hidden-table');
            }
        });
    });
})(jQuery);

jQuery(document).on('keyup change keydown keypress oninput', '.input-text.qty.text', function (event) {
    console.log('variation qty changes...');
    let tr = document.querySelector('.simply-tire-price-grid:not(.hidden-table) > tbody').rows;
    let qty = jQuery(event.target).first().val();
    let i;
    var priceSpanBdi = document.querySelector('.woocommerce-variation-price > .price > .woocommerce-Price-amount > bdi');
    let priceSpanBdiSpan = priceSpanBdi.querySelector('.woocommerce-Price-currencySymbol');
    for (i = 0; i < tr.length ; i++) {
        let td1;
        let td = tr[i].querySelector('.simply-tire-price')
        if(i == tr.length -1){
            price = Number(tr[i].querySelector('.simply-tire-price').querySelectorAll('span')[1].textContent);
            var text = priceSpanBdi.textContent;
            // Replace the price in the text with the new price
            var newText = text.replace(/\d+(\.\d+)?([^\d]*)/g, price.toFixed(2) + "$2");
            priceSpanBdi.textContent = newText;
            document.getElementById('realprice').value = price
            break;
        }else{
            td1 = tr[i + 1].querySelector('.simply-tire-quantity');
        }
        if (parseInt(qty) < parseInt(tr[i+1].querySelector('.simply-tire-quantity').textContent)) {
            price = Number(tr[i].querySelector('.simply-tire-price').querySelectorAll('span')[1].textContent);
            let  realprice = tr[i].querySelector('.simply-tire-price').querySelectorAll('span')[1].textContent
            var text = priceSpanBdi.textContent;
            // Replace the price in the text with the new price
            var newText = text.replace(/\d+(\.\d+)?([^\d]*)/g, price.toFixed(2) + "$2");
            priceSpanBdi.textContent = newText;
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
        document.getElementById('realprice').value = price
    }
})

