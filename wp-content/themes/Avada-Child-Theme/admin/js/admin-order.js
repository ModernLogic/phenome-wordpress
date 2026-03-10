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
    function getPendingCount($table) {
        return parseInt($table.data('pending-requests') || 0, 10);
    }

    function increasePending($table) {
        var current = getPendingCount($table);
        if (current === 0) {
            $table.block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: .6
                }
            });
        }
        $table.data('pending-requests', current + 1);
    }

    function decreasePending($table) {
        var current = getPendingCount($table);
        var next = Math.max(0, current - 1);
        $table.data('pending-requests', next);
        if (next === 0) {
            $table.unblock();
        }
    }

    $('.add_result_meta').each(function() {
        var initialValue = $(this).val() || '';
        $(this).data('last-sent-value', initialValue);
    });

    $('.add_result_meta').on('change', function(e) {
        e.preventDefault();
        var $this = $(this);
        var $row = $this.closest('tr');
        var $table = $this.closest('table');
        var currentValue = $this.val() || '';
        var lastSentValue = $this.data('last-sent-value') || '';
        var existingTimer = $this.data('save-timer');

        if (existingTimer) {
            clearTimeout(existingTimer);
        }

        // Avoid sending duplicate no-op writes.
        if (currentValue === lastSentValue) {
            return;
        }

        var timer = setTimeout(function() {
            var activeRequest = $this.data('active-request');
            if (activeRequest && typeof activeRequest.abort === 'function') {
                activeRequest.abort();
            }

            $row.addClass('process');
            $row.find('td:nth-child(2)').text('Saving...');
            increasePending($table);

            var data = {
                action: 'add_result_meta',
                item_id: $this.attr('data-itemid'),
                meta_key: $this.attr('data-type'),
                meta_index: $this.attr('data-index'),
                meta_parent: $this.attr('data-parent'),
                meta_value: currentValue,
                nonce: custom_admin_ajax.nonce
            };

            var request = $.ajax({
                url: custom_admin_ajax.ajax_url,
                type: 'POST',
                data: data,
                timeout: 15000
            });
            $this.data('active-request', request);
            request
                .done(function(response) {
                    if ($this.data('active-request') !== request) return;
                    if (response && response.success) {
                        $this.data('last-sent-value', currentValue);
                        $row.find('td:nth-child(2)').text('Complete');
                    } else {
                        $row.find('td:nth-child(2)').text('Pending');
                    }
                })
                .fail(function() {
                    if ($this.data('active-request') !== request) return;
                    $row.find('td:nth-child(2)').text('Pending');
                })
                .always(function() {
                    if ($this.data('active-request') !== request) return;
                    $this.removeData('active-request');
                    $row.removeClass('process');
                    decreasePending($table);
                });
        }, 300);

        $this.data('save-timer', timer);
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
