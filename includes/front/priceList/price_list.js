
jQuery(document).ready(function ($) {

    function setInitialPriceOnQtyOne(){
        let priceGridRows = document.getElementById('simply-tire-price-grid-rows');
        if (priceGridRows) {
            let initQty = priceGridRows.querySelectorAll('tr')[0].querySelectorAll('td')[0].textContent;
            let initialPrice   = priceGridRows.querySelectorAll('tr')[0].querySelectorAll('td')[1].querySelectorAll('span')[0].textContent;
            if(1 == initQty){
                let priceSpan = document.querySelector('.woocommerce-Price-amount');
                let priceSpanBdi = priceSpan.querySelector('bdi');
                // Check if priceSpanBdi has child nodes and the first node is of type text
                if (priceSpanBdi.childNodes.length > 0 && priceSpanBdi.childNodes[0].nodeType === Node.TEXT_NODE) {
                    // Update the text content of the first child node
                    priceSpanBdi.childNodes[0].textContent = initialPrice;
                    console.log('Coin position on the right');
                }  else {
                    priceSpanBdi.childNodes[1].textContent = initialPrice;
                    console.log('Coin position on the left');
                }

            }
        }
    }
    // Call the function
    setInitialPriceOnQtyOne();

    $(document).on('keyup change keydown keypress oninput', '.input-text.qty.text', function (event) {
        console.log('qty changes...');
        let tr = document.getElementById('simply-tire-price-grid-rows');
        if (tr) {
            tr = tr.rows;
            // Rest of the code that uses the 'tr' variable...
        } else {
            console.log("Element with ID 'simply-tire-price-grid-rows' not found.");
        }
        let qty = $(event.target).first().val();
        let entry = document.querySelector('.summary.entry-summary');
        if (entry) {
            let priceSpan = entry.querySelector('.woocommerce-Price-amount');
            if (priceSpan) {
                let priceSpanBdi = priceSpan.querySelector('bdi');
                let priceSpanBdiSpan = priceSpanBdi.querySelector('.woocommerce-Price-currencySymbol');
                let price;
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
            } else {
                console.log("Element with class 'woocommerce-Price-amount' not found.");
            }
        } else {
            console.log("Element with class 'summary entry-summary' not found.");
        }

    });
});