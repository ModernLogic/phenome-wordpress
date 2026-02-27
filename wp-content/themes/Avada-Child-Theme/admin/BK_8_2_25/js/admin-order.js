function base64ToArrayBuffer(base64) {
    var binaryString = window.atob(base64);
    var binaryLen = binaryString.length;
    var bytes = new Uint8Array(binaryLen);
    for (var i = 0; i < binaryLen; i++) {
        var ascii = binaryString.charCodeAt(i);
        bytes[i] = ascii;
    }
    return bytes.buffer;
}

jQuery(document).ready(function($) {
    $('.add_result_meta').on('change', function(e) {
        var a = $(this).closest('table');
        $(a).block({
            message: null,
            overlayCSS: {
                background: "#fff",
                opacity: .6
            }
        });
        e.preventDefault();
        var $this=$(this);
        $(this).closest('tr').addClass('process');
        $(this).closest('tr').find('td:nth-child(2)').html('Complete');
        var data = {
            action: 'add_result_meta',
            item_id:  $(this).attr('data-itemid'),
            meta_key:  $(this).attr('data-type'),
            meta_index:  $(this).attr('data-index'),
            meta_parent:$(this).attr('data-parent'),
            meta_value: $(this).val(),
            nonce: custom_admin_ajax.nonce
        };

        $.post(custom_admin_ajax.ajax_url, data, function(response) {
            if (response.success) {
                $this.closest('tr').removeClass('process');
                $(a).unblock();
            } else {
                $this.closest('tr').find('td:nth-child(2)').html('pending');
                $this.closest('tr').removeClass('process');
                $(a).unblock();
            }
        });
    });
    

   
  $('.generate-labels').on('click', function(event) {
    var a = $('#woocommerce-order-items');
            $(a).block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: .6
                }
            });
            event.preventDefault(); // Prevent default form submission behavior
            

            var order_id = $(this).data('order-id');
            $.ajax({
                url: custom_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    'action': 'generate_labels',
                    'order_id': order_id
                },
                success: function(data) {
                    $(a).unblock();
                    var pdfData = base64ToArrayBuffer(data);
                    var blob = new Blob([pdfData], { type: 'application/pdf' });
                    var url = URL.createObjectURL(blob);

                    // Create a download link
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = 'test_labels_'+order_id+'.pdf';

                    // Trigger the download
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Revoke the object URL
                    URL.revokeObjectURL(url);
                
                },
                error: function() {
                    $(a).unblock();
                    console.log('Error generating PDF');
                }
            });
        });


  $('.generate-pdf').on('click', function(event) {
            event.preventDefault(); // Prevent default form submission behavior
            var a = $('#woocommerce-order-items');
            $(a).block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: .6
                }
            });
            

            var order_id = $(this).data('order-id');
            $.ajax({
                url: custom_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    'action': 'generate_pdf',
                    'order_id': order_id
                },
                // dataType: 'blob',
                success: function(data) {
                    $(a).unblock();
                    var pdfData = base64ToArrayBuffer(data);
                    var blob = new Blob([pdfData], { type: 'application/pdf' });
                    var url = URL.createObjectURL(blob);

                    // Create a download link
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = 'test_details_'+order_id+'.pdf';

                    // Trigger the download
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Revoke the object URL
                    URL.revokeObjectURL(url);

                },
                error: function() {
                    $(a).unblock();
                    console.log('Error generating PDF');
                }
            });
        });

        $('.generate-emaill').on('click', function(event) {
            var a = $('#woocommerce-order-items');
            $(a).block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: .6
                }
            });
            event.preventDefault(); // Prevent default form submission behavior

            var order_id = $(this).data('order-id');
            $.ajax({
                url: custom_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    'action': 'generate_emaill',
                    'order_id': order_id
                },
                success: function(data) {
                    $(a).unblock();
                   alert('Email has been sent to the customer.');
                
                },
                error: function() {
                    $(a).unblock();
                    alert('Something wrong happened');
                }
            });
        });

});
